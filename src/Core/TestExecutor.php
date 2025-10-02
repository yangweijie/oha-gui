<?php

namespace OhaGui\Core;

use InvalidArgumentException;
use OhaGui\Models\TestResult;
use OhaGui\Models\TestConfiguration;
use Psl\Shell;
use Psl\Env;
use Psl\Str;
use Psl\Vec;
use Psl\Filesystem;
use RuntimeException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * TestExecutor - Handles asynchronous execution of oha commands
 * 
 * This class manages the execution of oha commands with real-time output capture,
 * process monitoring, and proper cleanup functionality.
 */
class TestExecutor
{
    private ?Process $process = null;
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
     * @throws RuntimeException If command execution fails to start
     */
    public function executeTest(string $command, ?TestConfiguration $config = null, ?callable $outputCallback = null, ?callable $completionCallback = null): void
    {
        if ($this->isRunning) {
            throw new RuntimeException('A test is already running. Stop the current test before starting a new one.');
        }
        
        // Validate command before execution
        if (empty(trim($command))) {
            throw new InvalidArgumentException('Command cannot be empty');
        }
        
        // Check if oha binary exists
        $this->validateOhaBinary($command);
        
        $this->testConfig = $config;
        $this->outputCallback = $outputCallback;
        $this->completionCallback = $completionCallback;
        $this->outputBuffer = '';
        
        // Parse command into parts
        $commandParts = $this->parseCommand($command);
        
        // Set environment variables for better error reporting
        $env = Env\get_vars();
        $env['LC_ALL'] = 'C'; // Ensure consistent output format
        
        // Create Symfony Process
        $this->process = new Process($commandParts, null, $env);
        
        // Set timeout if specified
        if ($this->executionTimeout !== null) {
            $this->process->setTimeout($this->executionTimeout);
        } else {
            $this->process->setTimeout(null); // No timeout
        }
        
        try {
            // Start the process
            $this->process->start();
            $this->isRunning = true;
            
            // Start monitoring the process in a separate loop
            $this->monitorProcess();
        } catch (\Exception $e) {
            $this->isRunning = false;
            throw new RuntimeException('Failed to start oha process: ' . $e->getMessage() . ' (Command: ' . $command . ')');
        }
    }
    
    /**
     * Stop the currently running test
     * 
     * @return bool True if test was stopped successfully, false if no test was running
     */
    public function stopTest(): bool
    {
        if (!$this->isRunning || $this->process === null) {
            return false;
        }
        
        try {
            // Terminate the process
            $this->process->stop(5); // Give 5 seconds for graceful termination
            
            // Read any remaining output
            $this->readRemainingOutput();
            
            // Call output callback with termination message
            if ($this->outputCallback) {
                call_user_func($this->outputCallback, "\n[Test stopped by user]\n");
            }
            
            // Create a test result for the stopped test
            if ($this->completionCallback) {
                $testResult = new TestResult();
                $testResult->rawOutput = $this->outputBuffer . "\n[Test stopped by user]\n";
                $testResult->requestsPerSecond = 0.0;
                $testResult->totalRequests = 0;
                $testResult->failedRequests = 0;
                $testResult->successRate = 0.0;
                call_user_func($this->completionCallback, $testResult);
            }
            
            $this->isRunning = false;
            $this->process = null;
            
            return true;
        } catch (\Exception $e) {
            // Log the error but still consider the stop operation successful
            error_log("Error stopping process: " . $e->getMessage());
            $this->isRunning = false;
            $this->process = null;
            return true;
        }
    }

    
    /**
     * Check if a test is currently running
     * 
     * @return bool True if test is running, false otherwise
     */
    public function isRunning(): bool
    {
        if (!$this->isRunning || $this->process === null) {
            return false;
        }
        
        // Check if process is still running
        if ($this->process->isRunning()) {
            return true;
        } else {
            // Process has completed
            $this->isRunning = false;
            $this->handleProcessCompletion();
            return false;
        }
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
        if (!$this->isRunning || $this->process === null) {
            return;
        }
        
        // Use a separate process to monitor the process
        // We'll check the process status periodically
        // This approach is simpler and more reliable
    }
    
    /**
     * Handle process completion and cleanup
     */
    private function handleProcessCompletion(): void
    {
        if ($this->process === null) {
            return;
        }
        
        // Get process output
        $output = $this->process->getOutput();
        $errorOutput = $this->process->getErrorOutput();
        
        // Append to output buffer
        $this->outputBuffer .= $output . $errorOutput;
        
        // Call output callback with final output
        if ($this->outputCallback) {
            if (!empty($output)) {
                call_user_func($this->outputCallback, $output);
            }
            if (!empty($errorOutput)) {
                call_user_func($this->outputCallback, $errorOutput);
            }
        }
        
        // Get exit code
        $exitCode = $this->process->getExitCode();
        
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
        
        $this->isRunning = false;
        $this->process = null;
    }

    /**
     * Handle execution timeout
     */
    private function handleTimeout(): void
    {
        if ($this->process === null) {
            return;
        }
        
        $timeoutMessage = "\n[Timeout] Test execution timed out after {$this->executionTimeout} seconds\n";
        $this->outputBuffer .= $timeoutMessage;
        
        if ($this->outputCallback) {
            call_user_func($this->outputCallback, $timeoutMessage);
        }
        
        // Terminate the process
        $this->process->stop(5); // Give 5 seconds for graceful termination
        
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
        
        $this->isRunning = false;
        $this->process = null;
    }
    
    /**
     * Read any remaining output from the process
     */
    private function readRemainingOutput(): void
    {
        if ($this->process === null) {
            return;
        }
        
        // Get any remaining output
        $output = $this->process->getOutput();
        $errorOutput = $this->process->getErrorOutput();
        
        // Append to output buffer
        $this->outputBuffer .= $output . $errorOutput;
        
        // Call output callback with remaining output
        if ($this->outputCallback) {
            if (!empty($output)) {
                call_user_func($this->outputCallback, $output);
            }
            if (!empty($errorOutput)) {
                call_user_func($this->outputCallback, $errorOutput);
            }
        }
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
     * Execute a test synchronously (blocking)
     * 
     * This is a convenience method for cases where synchronous execution is preferred.
     * 
     * @param string $command The oha command to execute
     * @param int $timeoutSeconds Maximum time to wait for completion (default: 300 seconds)
     * @return TestResult The test result
     * @throws RuntimeException If execution fails or times out
     */
    public function executeTestSync(string $command, int $timeoutSeconds = 300): TestResult
    {
        if ($this->isRunning) {
            throw new RuntimeException('A test is already running. Stop the current test before starting a new one.');
        }
        
        // Validate command before execution
        if (empty(trim($command))) {
            throw new InvalidArgumentException('Command cannot be empty');
        }
        
        // Check if oha binary exists
        $this->validateOhaBinary($command);
        
        // Parse command into parts
        $commandParts = $this->parseCommand($command);
        
        // Set environment variables for better error reporting
        $env = Env\get_vars();
        $env['LC_ALL'] = 'C'; // Ensure consistent output format
        
        // Set timeout
        $this->setTimeout($timeoutSeconds);
        $this->executionStartTime = time();
        
        // Create Symfony Process
        $process = new Process($commandParts, null, $env);
        $process->setTimeout($timeoutSeconds);
        
        try {
            // Execute the command synchronously
            $process->mustRun();
            
            // Get output and error output
            $output = $process->getOutput();
            $errorOutput = $process->getErrorOutput();
            $exitCode = $process->getExitCode();
            
            // Combine output and error output
            $fullOutput = $output . $errorOutput;
            
            // Add error information to output if process failed
            if ($exitCode !== 0) {
                $errorMessage = $this->getProcessErrorMessage($exitCode, $fullOutput);
                $fullOutput .= "\n[Process Error] " . $errorMessage . "\n";
            }
            
            // Create and return test result
            $testResult = $this->createTestResultFromOutput($fullOutput, $exitCode);
            
            return $testResult;
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
            // Handle timeout specifically
            throw new RuntimeException('Test execution timed out after ' . $timeoutSeconds . ' seconds');
        } catch (\Exception $e) {
            throw new RuntimeException('Failed to execute oha process: ' . $e->getMessage() . ' (Command: ' . $command . ')');
        }
    }
    
    /**
     * Validate that oha binary exists and is executable
     * 
     * @param string $command The command to validate
     * @throws RuntimeException If oha binary is not found or not executable
     */
    private function validateOhaBinary(string $command): void
    {
        // Extract binary path from command (first argument)
        $parts = $this->parseCommand($command);
        $binaryPath = $parts[0] ?? '';
        
        if (empty($binaryPath)) {
            throw new RuntimeException('No binary path found in command');
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
                throw new RuntimeException(
                    'oha binary not found in PATH. Please install oha or ensure it is in your system PATH. ' .
                    'Visit https://github.com/hatoo/oha for installation instructions.'
                );
            }
        } else {
            // Full path - check if file exists and is executable
            if (!Filesystem\exists($binaryPath)) {
                throw new RuntimeException('oha binary not found at: ' . $binaryPath);
            }
            
            if (!Filesystem\is_executable($binaryPath)) {
                throw new RuntimeException('oha binary is not executable: ' . $binaryPath);
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
     * Update process monitoring (should be called periodically from GUI event loop)
     * 
     * This method should be called regularly to ensure real-time output capture
     * and proper process completion handling.
     */
    public function update(): void
    {
        // Check if process is still running
        if ($this->isRunning && $this->process !== null) {
            if (!$this->process->isRunning()) {
                // Process has completed
                $this->isRunning = false;
                $this->handleProcessCompletion();
            } else {
                // Process is still running, check for new output
                $newOutput = $this->process->getIncrementalOutput();
                $newErrorOutput = $this->process->getIncrementalErrorOutput();
                
                if (!empty($newOutput) && $this->outputCallback) {
                    call_user_func($this->outputCallback, $newOutput);
                    $this->outputBuffer .= $newOutput;
                }
                
                if (!empty($newErrorOutput) && $this->outputCallback) {
                    call_user_func($this->outputCallback, $newErrorOutput);
                    $this->outputBuffer .= $newErrorOutput;
                }
                
                // Check for timeout
                if ($this->hasTimedOut()) {
                    $this->handleTimeout();
                }
            }
        }
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
