<?php

namespace OhaGui\Core;

use Exception;
use OhaGui\Core\ProcessErrorHandler;

/**
 * Test Executor
 * Handles asynchronous execution of oha commands with real-time output capture
 * and comprehensive error handling
 */
class TestExecutor
{
    /**
     * Initialize TestExecutor with error handling
     */
    public function __construct()
    {
        $this->errorHandler = new ProcessErrorHandler();
    }
    private $process = null;
    private $pipes = [];
    private $isRunning = false;
    private $output = '';
    private $exitCode = 0;
    private $outputCallback = null;
    private $errorCallback = null;
    private $completionCallback = null;
    private ProcessErrorHandler $errorHandler;
    private int $timeoutSeconds = 0;
    private int $startTime = 0;

    /**
     * Execute oha test command asynchronously with comprehensive error handling
     * 
     * @param string $command The oha command to execute
     * @param callable|null $outputCallback Callback for real-time output (receives string)
     * @param callable|null $errorCallback Callback for error handling (receives error array)
     * @param callable|null $completionCallback Callback when test completes (receives exit code, error array)
     * @param int $timeoutSeconds Maximum execution time (0 = no timeout)
     * @return bool True if command started successfully
     * @throws Exception If command execution fails to start
     */
    public function executeTest(
        $command, 
        $outputCallback = null,
        $errorCallback = null,
        $completionCallback = null,
        $timeoutSeconds = 0
    ) {
        if ($this->isRunning) {
            throw new Exception('A test is already running. Stop the current test before starting a new one.');
        }

        // Validate command before execution
        $commandValidation = $this->errorHandler->validateCommand($command);
        if (!$commandValidation['valid']) {
            $error = [
                'type' => ProcessErrorHandler::ERROR_INVALID_ARGUMENTS,
                'message' => 'Invalid command: ' . implode(', ', $commandValidation['errors']),
                'suggestion' => 'Check the command parameters and try again'
            ];
            
            if ($errorCallback) {
                call_user_func($errorCallback, $error);
            }
            
            throw new Exception($error['message']);
        }

        // Validate oha binary availability
        $binaryValidation = $this->errorHandler->validateOhaBinary();
        if (!$binaryValidation['available']) {
            $error = $binaryValidation['errors'][0] ?? [
                'type' => ProcessErrorHandler::ERROR_BINARY_NOT_FOUND,
                'message' => 'oha binary not available',
                'suggestion' => 'Install oha and ensure it\'s in your PATH'
            ];
            
            if ($errorCallback) {
                call_user_func($errorCallback, $error);
            }
            
            throw new Exception($error['message']);
        }

        $this->outputCallback = $outputCallback;
        $this->errorCallback = $errorCallback;
        $this->completionCallback = $completionCallback;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->startTime = time();
        $this->output = '';
        $this->exitCode = 0;

        // Define pipe descriptors for process communication
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        // Start the process
        $this->process = proc_open($command, $descriptors, $this->pipes);

        if (!is_resource($this->process)) {
            $error = [
                'type' => ProcessErrorHandler::ERROR_PROCESS_START_FAILED,
                'message' => 'Failed to start oha process',
                'suggestion' => 'Check if oha is properly installed and accessible',
                'details' => ['command' => $command]
            ];
            
            if ($this->errorCallback) {
                call_user_func($this->errorCallback, $error);
            }
            
            throw new Exception($error['message'] . ': ' . $command);
        }

        // Set streams to non-blocking mode for real-time output
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);

        $this->isRunning = true;

        // Close stdin as we don't need to send input
        fclose($this->pipes[0]);

        return true;
    }

    /**
     * Check if a test is currently running
     * 
     * @return bool True if test is running
     */
    public function isRunning()
    {
        if (!$this->isRunning || !is_resource($this->process)) {
            return false;
        }

        // Check for timeout
        if ($this->timeoutSeconds > 0 && (time() - $this->startTime) >= $this->timeoutSeconds) {
            $this->handleTimeout();
            return false;
        }

        // Check process status
        $status = proc_get_status($this->process);
        
        if (!$status['running']) {
            $this->isRunning = false;
            $this->exitCode = $status['exitcode'];
            
            // Read any remaining output
            $this->readRemainingOutput();
            
            // Analyze error if process failed
            $error = null;
            if ($this->exitCode !== 0) {
                $error = $this->errorHandler->analyzeProcessError(
                    $this->exitCode,
                    $this->output,
                    'oha command'
                );
                
                if ($error && $this->errorCallback) {
                    call_user_func($this->errorCallback, $error);
                }
            }
            
            // Close pipes and process
            $this->cleanup();
            
            // Call completion callback
            if ($this->completionCallback) {
                call_user_func($this->completionCallback, $this->exitCode, $error);
            }
            
            return false;
        }

        // Read available output
        $this->readOutput();

        return true;
    }

    /**
     * Stop the currently running test
     * 
     * @return bool True if test was stopped successfully
     */
    public function stopTest()
    {
        if (!$this->isRunning || !is_resource($this->process)) {
            return true;
        }

        // Terminate the process
        $terminated = proc_terminate($this->process);
        
        if ($terminated) {
            // Wait a moment for graceful termination
            usleep(100000); // 100ms
            
            // Force kill if still running
            $status = proc_get_status($this->process);
            if ($status['running']) {
                proc_terminate($this->process, 9); // SIGKILL
            }
        }

        $this->isRunning = false;
        $this->cleanup();

        return $terminated;
    }

    /**
     * Get the complete output from the test
     * 
     * @return string The complete output
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Get the exit code of the completed test
     * 
     * @return int The exit code (0 for success)
     */
    public function getExitCode()
    {
        return $this->exitCode;
    }

    /**
     * Read available output from the process
     */
    private function readOutput(): void
    {
        if (!isset($this->pipes[1]) || !isset($this->pipes[2])) {
            return;
        }

        // Read from stdout
        $stdout = stream_get_contents($this->pipes[1]);
        if ($stdout !== false && $stdout !== '') {
            $this->output .= $stdout;
            if ($this->outputCallback) {
                call_user_func($this->outputCallback, $stdout);
            }
        }

        // Read from stderr
        $stderr = stream_get_contents($this->pipes[2]);
        if ($stderr !== false && $stderr !== '') {
            $this->output .= $stderr;
            if ($this->errorCallback) {
                call_user_func($this->errorCallback, $stderr);
            } elseif ($this->outputCallback) {
                // If no error callback, send to output callback
                call_user_func($this->outputCallback, $stderr);
            }
        }
    }

    /**
     * Read any remaining output when process completes
     */
    private function readRemainingOutput(): void
    {
        if (!isset($this->pipes[1]) || !isset($this->pipes[2])) {
            return;
        }

        // Set blocking mode to read all remaining data
        stream_set_blocking($this->pipes[1], true);
        stream_set_blocking($this->pipes[2], true);

        // Read remaining stdout
        $stdout = stream_get_contents($this->pipes[1]);
        if ($stdout !== false && $stdout !== '') {
            $this->output .= $stdout;
            if ($this->outputCallback) {
                call_user_func($this->outputCallback, $stdout);
            }
        }

        // Read remaining stderr
        $stderr = stream_get_contents($this->pipes[2]);
        if ($stderr !== false && $stderr !== '') {
            $this->output .= $stderr;
            if ($this->errorCallback) {
                call_user_func($this->errorCallback, $stderr);
            } elseif ($this->outputCallback) {
                call_user_func($this->outputCallback, $stderr);
            }
        }
    }

    /**
     * Clean up process resources
     */
    private function cleanup(): void
    {
        // Close pipes
        if (isset($this->pipes[1]) && is_resource($this->pipes[1])) {
            fclose($this->pipes[1]);
        }
        if (isset($this->pipes[2]) && is_resource($this->pipes[2])) {
            fclose($this->pipes[2]);
        }

        // Close process
        if (is_resource($this->process)) {
            proc_close($this->process);
        }

        $this->pipes = [];
        $this->process = null;
    }

    /**
     * Get process status information
     * 
     * @return array|null Process status or null if no process
     */
    public function getProcessStatus()
    {
        if (!is_resource($this->process)) {
            return null;
        }

        return proc_get_status($this->process);
    }

    /**
     * Wait for the test to complete (blocking)
     * 
     * @param int $timeoutSeconds Maximum time to wait (0 = no timeout)
     * @return bool True if test completed, false if timeout
     */
    public function waitForCompletion($timeoutSeconds = 0)
    {
        $startTime = time();
        
        while ($this->isRunning()) {
            usleep(100000); // 100ms
            
            if ($timeoutSeconds > 0 && (time() - $startTime) >= $timeoutSeconds) {
                return false; // Timeout
            }
        }
        
        return true;
    }

    /**
     * Execute a test synchronously (blocking)
     * 
     * @param string $command The oha command to execute
     * @param int $timeoutSeconds Maximum execution time (0 = no timeout)
     * @return array Result with 'success', 'output', 'exitCode'
     * @throws Exception If command execution fails
     */
    public function executeTestSync($command, $timeoutSeconds = 0)
    {
        $output = '';
        
        $this->executeTest($command, function($data) use (&$output) {
            $output .= $data;
        });
        
        $completed = $this->waitForCompletion($timeoutSeconds);
        
        if (!$completed) {
            $this->stopTest();
            throw new Exception('Test execution timed out after ' . $timeoutSeconds . ' seconds');
        }
        
        return [
            'success' => $this->exitCode === 0,
            'output' => $this->output,
            'exitCode' => $this->exitCode
        ];
    }

    /**
     * Handle process timeout
     */
    private function handleTimeout(): void
    {
        $this->isRunning = false;
        
        $error = $this->errorHandler->handleProcessTimeout(
            $this->timeoutSeconds,
            'oha command'
        );
        
        if ($this->errorCallback) {
            call_user_func($this->errorCallback, $error);
        }
        
        // Force stop the process
        $this->stopTest();
        
        if ($this->completionCallback) {
            call_user_func($this->completionCallback, -1, $error);
        }
    }

    /**
     * Validate oha binary availability
     * 
     * @return array Validation result
     */
    public function validateOhaBinary(): array
    {
        return $this->errorHandler->validateOhaBinary();
    }

    /**
     * Get oha installation instructions
     * 
     * @return string Installation instructions
     */
    public function getOhaInstallationInstructions(): string
    {
        return $this->errorHandler->getOhaInstallationInstructions();
    }

    /**
     * Get user-friendly error message
     * 
     * @param array $error Error details
     * @return string User-friendly message
     */
    public function getUserFriendlyErrorMessage(array $error): string
    {
        return $this->errorHandler->getUserFriendlyErrorMessage($error);
    }

    /**
     * Check if last execution had errors
     * 
     * @return bool True if there were errors
     */
    public function hasErrors(): bool
    {
        return $this->exitCode !== 0;
    }

    /**
     * Get detailed error analysis for last execution
     * 
     * @param string $command Command that was executed
     * @return array|null Error details or null if no error
     */
    public function getLastError(string $command = ''): ?array
    {
        $analysis = $this->errorHandler->analyzeProcessError(
            $this->exitCode,
            $this->output,
            $command
        );
        
        // Return null for success cases
        if ($analysis['type'] === 'success') {
            return null;
        }
        
        return $analysis;
    }

    /**
     * Execute test with enhanced error handling and return detailed result
     * 
     * @param string $command Command to execute
     * @param int $timeoutSeconds Timeout in seconds
     * @return array Detailed execution result
     */
    public function executeTestWithErrorHandling(string $command, int $timeoutSeconds = 0): array
    {
        $result = [
            'success' => false,
            'output' => '',
            'exit_code' => 0,
            'error' => null,
            'warnings' => []
        ];

        try {
            // Validate binary first
            $binaryValidation = $this->validateOhaBinary();
            if (!$binaryValidation['available']) {
                $result['error'] = $binaryValidation['errors'][0] ?? [
                    'type' => ProcessErrorHandler::ERROR_BINARY_NOT_FOUND,
                    'message' => 'oha binary not available'
                ];
                return $result;
            }

            // Add any warnings from binary validation
            $result['warnings'] = $binaryValidation['warnings'] ?? [];

            // Execute the test
            $this->executeTest($command, null, null, null, $timeoutSeconds);
            
            // Wait for completion
            $completed = $this->waitForCompletion($timeoutSeconds);
            
            if (!$completed && $timeoutSeconds > 0) {
                $this->stopTest();
                $result['error'] = $this->errorHandler->handleProcessTimeout($timeoutSeconds, $command);
                return $result;
            }

            $result['success'] = $this->exitCode === 0;
            $result['output'] = $this->output;
            $result['exit_code'] = $this->exitCode;

            if (!$result['success']) {
                $result['error'] = $this->getLastError($command);
            }

        } catch (Exception $e) {
            $result['error'] = [
                'type' => ProcessErrorHandler::ERROR_UNKNOWN,
                'message' => $e->getMessage(),
                'suggestion' => 'Check the error details and try again'
            ];
        }

        return $result;
    }

    /**
     * Public cleanup method for external resource management
     * 
     * @return void
     */
    public function cleanupResources(): void
    {
        if ($this->isRunning) {
            $this->stopTest();
        }
        
        // Clear callbacks to prevent memory leaks
        $this->outputCallback = null;
        $this->errorCallback = null;
        $this->completionCallback = null;
        
        // Clear output buffer
        $this->output = '';
    }

    /**
     * Destructor to ensure cleanup
     */
    public function __destruct()
    {
        if ($this->isRunning) {
            $this->stopTest();
        }
    }
}