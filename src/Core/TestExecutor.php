<?php

namespace OhaGui\Core;

use OhaGui\Models\TestResult;
use OhaGui\Models\TestConfiguration;
use Psl\Shell;
use Psl\Env;
use Psl\Str;
use Psl\Vec;
use Psl\Filesystem;

/**
 * TestExecutor - Handles asynchronous execution of oha commands
 * 
 * This class manages the execution of oha commands with real-time output capture,
 * process monitoring, and proper cleanup functionality.
 */
class TestExecutor
{
    private $process = null;
    private array $pipes = [];
    private bool $isRunning = false;
    private string $outputBuffer = '';
    private $outputCallback = null;
    private $completionCallback = null;
    private ?int $executionTimeout = null;
    private ?int $executionStartTime = null;
    private ?TestConfiguration $testConfig = null;
    
    /**
     * Execute oha command asynchronously with real-time output capture
     * 
     * @param string $command The oha command to execute
     * @param TestConfiguration|null $config The test configuration (optional)
     * @param callable|null $outputCallback Callback for real-time output (receives string output)
     * @param callable|null $completionCallback Callback when test completes (receives TestResult)
     * @throws \RuntimeException If command execution fails to start
     */
    public function executeTest(string $command, ?TestConfiguration $config = null, ?callable $outputCallback = null, ?callable $completionCallback = null): void
    {
        if ($this->isRunning) {
            throw new \RuntimeException('A test is already running. Stop the current test before starting a new one.');
        }
        
        // Validate command before execution
        if (empty(trim($command))) {
            throw new \InvalidArgumentException('Command cannot be empty');
        }
        
        // Check if oha binary exists
        $this->validateOhaBinary($command);
        
        $this->testConfig = $config;
        $this->outputCallback = $outputCallback;
        $this->completionCallback = $completionCallback;
        $this->outputBuffer = '';
        
        // Parse command into parts
        $commandParts = $this->parseCommand($command);
        $binary = $commandParts[0];
        $arguments = array_slice($commandParts, 1);
        
        // Set environment variables for better error reporting
        $env = Env\get_vars();
        $env['LC_ALL'] = 'C'; // Ensure consistent output format
        
        // Since PSL doesn't have an execute_async function, we'll use proc_open directly
        // but with better error handling and process management
        
        // Define pipe descriptors for process communication
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w']  // stderr
        ];
        
        // Start the process with error handling
        $this->process = proc_open($command, $descriptors, $this->pipes, null, $env);
        
        if (!is_resource($this->process)) {
            $error = error_get_last();
            $errorMsg = $error ? $error['message'] : 'Unknown error';
            throw new \RuntimeException('Failed to start oha process: ' . $errorMsg . ' (Command: ' . $command . ')');
        }
        
        // Check if process started successfully
        $status = proc_get_status($this->process);
        if (!$status['running'] && $status['exitcode'] !== -1) {
            $this->cleanup();
            throw new \RuntimeException('Process failed to start or exited immediately with code: ' . $status['exitcode']);
        }
        
        $this->isRunning = true;
        
        // Set pipes to non-blocking mode for real-time output
        // On Windows, stream_set_blocking may not work as expected, so we'll handle it gracefully
        if (!@stream_set_blocking($this->pipes[1], false)) {
            // On Windows, we'll use a different approach for non-blocking reads
            // This is a known limitation, but we can still proceed
            if (PHP_OS_FAMILY !== 'Windows') {
                $this->cleanup();
                throw new \RuntimeException('Failed to set stdout to non-blocking mode');
            }
        }
        
        if (!@stream_set_blocking($this->pipes[2], false)) {
            // On Windows, we'll use a different approach for non-blocking reads
            // This is a known limitation, but we can still proceed
            if (PHP_OS_FAMILY !== 'Windows') {
                $this->cleanup();
                throw new \RuntimeException('Failed to set stderr to non-blocking mode');
            }
        }
        
        // Close stdin as we don't need to send input to oha
        fclose($this->pipes[0]);
        unset($this->pipes[0]);
        
        // Start monitoring the process
        $this->monitorProcess();
    }
    
    /**
     * Stop the currently running test
     * 
     * @return bool True if test was stopped successfully, false if no test was running
     */
    public function stopTest(): bool
    {
        if (!$this->isRunning || !is_resource($this->process)) {
            return false;
        }
        
        // Terminate the process
        $terminated = proc_terminate($this->process);
        
        if ($terminated) {
            $this->cleanup();
            
            // Call output callback with termination message
            if ($this->outputCallback) {
                call_user_func($this->outputCallback, "\n[Test stopped by user]\n");
            }
        }
        
        return $terminated;
    }
    
    /**
     * Check if a test is currently running
     * 
     * @return bool True if test is running, false otherwise
     */
    public function isRunning(): bool
    {
        if (!$this->isRunning) {
            return false;
        }
        
        // Check if process is still running
        if (is_resource($this->process)) {
            $status = proc_get_status($this->process);
            if (!$status['running']) {
                $this->isRunning = false;
                $this->handleProcessCompletion();
            }
        } else {
            $this->isRunning = false;
        }
        
        return $this->isRunning;
    }
    
    /**
     * Get the current output buffer
     * 
     * @return string The accumulated output from the test
     */
    public function getOutput(): string
    {
        return $this->outputBuffer;
    }
    
    /**
     * Monitor the running process for output and completion
     */
    private function monitorProcess(): void
    {
        // This method should be called periodically to check for output
        // In a GUI application, this would typically be called from a timer or event loop
        
        if (!$this->isRunning || !is_resource($this->process)) {
            return;
        }
        
        // Check for timeout
        if ($this->hasTimedOut()) {
            $this->handleTimeout();
            return;
        }
        
        // Read from stdout with error handling
        try {
            // On Windows, we need to handle streams differently
            if (PHP_OS_FAMILY === 'Windows') {
                // For Windows, we'll try to read in non-blocking mode without setting it explicitly
                $output = fread($this->pipes[1], 8192);
                if ($output !== false && $output !== '') {
                    $this->outputBuffer .= $output;
                    
                    if ($this->outputCallback) {
                        call_user_func($this->outputCallback, $output);
                    }
                }
            } else {
                // Unix-like systems
                $output = stream_get_contents($this->pipes[1]);
                if ($output !== false && $output !== '') {
                    $this->outputBuffer .= $output;
                    
                    if ($this->outputCallback) {
                        call_user_func($this->outputCallback, $output);
                    }
                }
            }
        } catch (\Exception $e) {
            if ($this->outputCallback) {
                call_user_func($this->outputCallback, "Error reading stdout: " . $e->getMessage() . "\n");
            }
        }
        
        // Read from stderr with error handling
        try {
            // On Windows, we need to handle streams differently
            if (PHP_OS_FAMILY === 'Windows') {
                // For Windows, we'll try to read in non-blocking mode without setting it explicitly
                $errorOutput = fread($this->pipes[2], 8192);
                if ($errorOutput !== false && $errorOutput !== '') {
                    $this->outputBuffer .= $errorOutput;
                    
                    if ($this->outputCallback) {
                        call_user_func($this->outputCallback, $errorOutput);
                    }
                }
            } else {
                // Unix-like systems
                $errorOutput = stream_get_contents($this->pipes[2]);
                if ($errorOutput !== false && $errorOutput !== '') {
                    $this->outputBuffer .= $errorOutput;
                    
                    if ($this->outputCallback) {
                        call_user_func($this->outputCallback, $errorOutput);
                    }
                }
            }
        } catch (\Exception $e) {
            if ($this->outputCallback) {
                call_user_func($this->outputCallback, "Error reading stderr: " . $e->getMessage() . "\n");
            }
        }
        
        // Check if process has completed
        $status = proc_get_status($this->process);
        if (!$status['running']) {
            $this->handleProcessCompletion();
        }
    }
    
    /**
     * Handle process completion and cleanup
     */
    private function handleProcessCompletion(): void
    {
        if (!is_resource($this->process)) {
            return;
        }
        
        // Read any remaining output with error handling
        try {
            // On Windows, we need to handle streams differently
            if (PHP_OS_FAMILY === 'Windows') {
                // For Windows, we'll try to read in non-blocking mode without setting it explicitly
                $remainingOutput = fread($this->pipes[1], 8192);
                if ($remainingOutput !== false && $remainingOutput !== '') {
                    $this->outputBuffer .= $remainingOutput;
                    
                    if ($this->outputCallback) {
                        call_user_func($this->outputCallback, $remainingOutput);
                    }
                }
            } else {
                // Unix-like systems
                $remainingOutput = stream_get_contents($this->pipes[1]);
                if ($remainingOutput !== false && $remainingOutput !== '') {
                    $this->outputBuffer .= $remainingOutput;
                    
                    if ($this->outputCallback) {
                        call_user_func($this->outputCallback, $remainingOutput);
                    }
                }
            }
        } catch (\Exception $e) {
            if ($this->outputCallback) {
                call_user_func($this->outputCallback, "Error reading final stdout: " . $e->getMessage() . "\n");
            }
        }
        
        try {
            // On Windows, we need to handle streams differently
            if (PHP_OS_FAMILY === 'Windows') {
                // For Windows, we'll try to read in non-blocking mode without setting it explicitly
                $remainingError = fread($this->pipes[2], 8192);
                if ($remainingError !== false && $remainingError !== '') {
                    $this->outputBuffer .= $remainingError;
                    
                    if ($this->outputCallback) {
                        call_user_func($this->outputCallback, $remainingError);
                    }
                }
            } else {
                // Unix-like systems
                $remainingError = stream_get_contents($this->pipes[2]);
                if ($remainingError !== false && $remainingError !== '') {
                    $this->outputBuffer .= $remainingError;
                    
                    if ($this->outputCallback) {
                        call_user_func($this->outputCallback, $remainingError);
                    }
                }
            }
        } catch (\Exception $e) {
            if ($this->outputCallback) {
                call_user_func($this->outputCallback, "Error reading final stderr: " . $e->getMessage() . "\n");
            }
        }
        
        // Get exit code and process status
        $status = proc_get_status($this->process);
        $exitCode = $status['exitcode'];
        
        // Add error information to output if process failed
        if ($exitCode !== 0) {
            $errorMessage = $this->getProcessErrorMessage($exitCode, $this->outputBuffer);
            $this->outputBuffer .= "\n[Process Error] " . $errorMessage . "\n";
            
            if ($this->outputCallback) {
                call_user_func($this->outputCallback, "\n[Process Error] " . $errorMessage . "\n");
            }
        }
        
        // Create TestResult and call completion callback
        if ($this->completionCallback) {
            $testResult = $this->createTestResultFromOutput($this->outputBuffer, $exitCode);
            call_user_func($this->completionCallback, $testResult);
        }
        
        $this->cleanup();
    }

    /**
     * Handle execution timeout
     */
    private function handleTimeout(): void
    {
        $timeoutMessage = "\n[Timeout] Test execution timed out after {$this->executionTimeout} seconds\n";
        $this->outputBuffer .= $timeoutMessage;
        
        if ($this->outputCallback) {
            call_user_func($this->outputCallback, $timeoutMessage);
        }
        
        // Terminate the process
        if (is_resource($this->process)) {
            proc_terminate($this->process, 9); // SIGKILL
        }
        
        // Create timeout result
        if ($this->completionCallback) {
            $testResult = new TestResult();
            $testResult->rawOutput = $this->outputBuffer;
            $testResult->requestsPerSecond = 0.0;
            $testResult->totalRequests = 0;
            $testResult->failedRequests = 0;
            $testResult->successRate = 0.0;
            
            call_user_func($this->completionCallback, $testResult);
        }
        
        $this->cleanup();
    }
    
    /**
     * Clean up process resources
     */
    private function cleanup(): void
    {
        if (is_resource($this->process)) {
            // Close pipes
            if (isset($this->pipes[1]) && is_resource($this->pipes[1])) {
                fclose($this->pipes[1]);
            }
            if (isset($this->pipes[2]) && is_resource($this->pipes[2])) {
                fclose($this->pipes[2]);
            }
            
            // Close process
            proc_close($this->process);
        }
        
        $this->process = null;
        $this->pipes = [];
        $this->isRunning = false;
    }
    
    /**
     * Create a TestResult object from the command output
     * 
     * @param string $output The raw output from oha command
     * @param int $exitCode The process exit code
     * @return TestResult The parsed test result
     */
    private function createTestResultFromOutput(string $output, int $exitCode): TestResult
    {
        $testResult = new TestResult();
        $testResult->rawOutput = $output;
        
        if ($exitCode !== 0) {
            // Test failed or was interrupted
            $testResult->requestsPerSecond = 0.0;
            $testResult->totalRequests = 0;
            $testResult->failedRequests = 0;
            $testResult->successRate = 0.0;
        } else {
            // Parse successful output - this will be enhanced in the ResultParser class
            // For now, just set default values
            $testResult->requestsPerSecond = 0.0;
            $testResult->totalRequests = 0;
            $testResult->failedRequests = 0;
            $testResult->successRate = 100.0;
        }
        
        return $testResult;
    }
    
    /**
     * Update process monitoring (should be called periodically from GUI event loop)
     * 
     * This method should be called regularly to ensure real-time output capture
     * and proper process completion handling.
     */
    public function update(): void
    {
        if ($this->isRunning) {
            $this->monitorProcess();
        }
    }
    
    /**
     * Execute a test synchronously (blocking)
     * 
     * This is a convenience method for cases where synchronous execution is preferred.
     * 
     * @param string $command The oha command to execute
     * @param int $timeoutSeconds Maximum time to wait for completion (default: 300 seconds)
     * @return TestResult The test result
     * @throws \RuntimeException If execution fails or times out
     */
    public function executeTestSync(string $command, int $timeoutSeconds = 300): TestResult
    {
        $output = '';
        $completed = false;
        $result = null;
        
        // Set up callbacks
        $outputCallback = function($data) use (&$output) {
            $output .= $data;
        };
        
        $completionCallback = function($testResult) use (&$completed, &$result) {
            $completed = true;
            $result = $testResult;
        };
        
        // Start the test
        $this->executeTest($command, null, $outputCallback, $completionCallback);
        
        // Wait for completion with timeout
        $startTime = time();
        while (!$completed && (time() - $startTime) < $timeoutSeconds) {
            $this->update();
            usleep(100000); // Sleep for 100ms
        }
        
        if (!$completed) {
            $this->stopTest();
            throw new \RuntimeException('Test execution timed out after ' . $timeoutSeconds . ' seconds');
        }
        
        return $result;
    }
    
    /**
     * Get process status information
     * 
     * @return array|null Process status array or null if no process is running
     */
    public function getProcessStatus(): ?array
    {
        if (!is_resource($this->process)) {
            return null;
        }
        
        return proc_get_status($this->process);
    }
    
    /**
     * Validate that oha binary exists and is executable
     * 
     * @param string $command The command to validate
     * @throws \RuntimeException If oha binary is not found or not executable
     */
    private function validateOhaBinary(string $command): void
    {
        // Extract binary path from command (first argument)
        $parts = $this->parseCommand($command);
        $binaryPath = $parts[0] ?? '';
        
        if (empty($binaryPath)) {
            throw new \RuntimeException('No binary path found in command');
        }
        
        // Remove quotes if present
        $binaryPath = trim($binaryPath, '"\'');
        
        // Check if it's just a binary name (in PATH) or a full path
        if (strpos($binaryPath, DIRECTORY_SEPARATOR) === false) {
            // Binary name only - first check local bin directory
            $localBinPath = getcwd() . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $binaryPath;
            if (Filesystem\exists($localBinPath) && Filesystem\is_executable($localBinPath)) {
                return;
            }
            
            // Then check if it exists in PATH
            $pathCommand = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
            $output = [];
            $returnCode = 0;
            
            exec($pathCommand . ' ' . escapeshellarg($binaryPath) . ' 2>/dev/null', $output, $returnCode);
            
            if ($returnCode !== 0 || empty($output)) {
                throw new \RuntimeException(
                    'oha binary not found in PATH. Please install oha or ensure it is in your system PATH. ' .
                    'Visit https://github.com/hatoo/oha for installation instructions.'
                );
            }
        } else {
            // Full path - check if file exists and is executable
            if (!Filesystem\exists($binaryPath)) {
                throw new \RuntimeException('oha binary not found at: ' . $binaryPath);
            }
            
            if (!Filesystem\is_executable($binaryPath)) {
                throw new \RuntimeException('oha binary is not executable: ' . $binaryPath);
            }
        }
    }

    /**
     * Handle process execution errors with detailed error reporting
     * 
     * @param int $exitCode Process exit code
     * @param string $output Process output
     * @return string Error message
     */
    private function getProcessErrorMessage(int $exitCode, string $output): string
    {
        $errorMessages = [
            1 => 'General error - check URL and parameters',
            2 => 'Misuse of shell command - invalid arguments',
            126 => 'Command invoked cannot execute - permission denied',
            127 => 'Command not found - oha binary not in PATH',
            128 => 'Invalid argument to exit',
            130 => 'Process terminated by user (Ctrl+C)',
            -1 => 'Process was terminated or killed'
        ];
        
        $baseMessage = $errorMessages[$exitCode] ?? 'Unknown error (exit code: ' . $exitCode . ')';
        
        // Extract specific error information from output
        $specificErrors = [];
        
        if (stripos($output, 'connection refused') !== false) {
            $specificErrors[] = 'Connection refused - server may be down or unreachable';
        }
        
        if (stripos($output, 'timeout') !== false) {
            $specificErrors[] = 'Request timeout - server not responding or network issues';
        }
        
        if (stripos($output, 'dns') !== false || stripos($output, 'name resolution') !== false) {
            $specificErrors[] = 'DNS resolution failed - check URL hostname';
        }
        
        if (stripos($output, 'ssl') !== false || stripos($output, 'tls') !== false) {
            $specificErrors[] = 'SSL/TLS error - certificate or encryption issues';
        }
        
        if (stripos($output, 'permission denied') !== false) {
            $specificErrors[] = 'Permission denied - check file permissions or user privileges';
        }
        
        if (stripos($output, 'invalid argument') !== false) {
            $specificErrors[] = 'Invalid command arguments - check configuration parameters';
        }
        
        $message = $baseMessage;
        if (!empty($specificErrors)) {
            $message .= ': ' . implode(', ', $specificErrors);
        }
        
        return $message;
    }

    /**
     * Set execution timeout for long-running tests
     * 
     * @param int $timeoutSeconds Maximum execution time in seconds
     */
    public function setTimeout(int $timeoutSeconds): void
    {
        $this->executionTimeout = $timeoutSeconds;
        $this->executionStartTime = time();
    }

    /**
     * Check if execution has timed out
     * 
     * @return bool True if execution has timed out
     */
    private function hasTimedOut(): bool
    {
        if (!isset($this->executionTimeout) || !isset($this->executionStartTime)) {
            return false;
        }
        
        return (time() - $this->executionStartTime) > $this->executionTimeout;
    }

    /**
     * Parse command string into parts
     * 
     * @param string $command The command string to parse
     * @return array The command parts
     */
    private function parseCommand(string $command): array
    {
        // Use Psl\Shell\escape_argument to properly parse the command
        // For now, we'll use a simple approach that works for most cases
        $parts = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = '';
        
        for ($i = 0; $i < strlen($command); $i++) {
            $char = $command[$i];
            
            if (!$inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
            } elseif ($inQuotes && $char === $quoteChar) {
                $inQuotes = false;
                $quoteChar = '';
            } elseif (!$inQuotes && $char === ' ') {
                if ($current !== '') {
                    $parts[] = $current;
                    $current = '';
                }
            } else {
                $current .= $char;
            }
        }
        
        if ($current !== '') {
            $parts[] = $current;
        }
        
        return $parts;
    }

    /**
     * Destructor to ensure proper cleanup
     */
    public function __destruct()
    {
        if ($this->isRunning) {
            $this->stopTest();
        }
    }
}