<?php

namespace OhaGui\Core;

use Exception;
use OhaGui\Utils\CrossPlatform;

/**
 * Process Error Handler
 * Provides comprehensive error handling for process execution
 */
class ProcessErrorHandler
{
    /**
     * Error types
     */
    public const ERROR_BINARY_NOT_FOUND = 'binary_not_found';
    public const ERROR_BINARY_NOT_EXECUTABLE = 'binary_not_executable';
    public const ERROR_PROCESS_START_FAILED = 'process_start_failed';
    public const ERROR_PROCESS_TIMEOUT = 'process_timeout';
    public const ERROR_PROCESS_CRASHED = 'process_crashed';
    public const ERROR_INVALID_ARGUMENTS = 'invalid_arguments';
    public const ERROR_PERMISSION_DENIED = 'permission_denied';
    public const ERROR_NETWORK_ERROR = 'network_error';
    public const ERROR_UNKNOWN = 'unknown';

    /**
     * Detect and validate oha binary availability
     * 
     * @return array Result with 'available', 'path', 'version', 'errors'
     */
    public function validateOhaBinary(): array
    {
        $result = [
            'available' => false,
            'path' => null,
            'version' => null,
            'errors' => [],
            'warnings' => []
        ];

        // Try to find oha binary
        $binaryPath = CrossPlatform::findOhaBinaryPath();
        
        if (!$binaryPath) {
            $result['errors'][] = $this->getOhaBinaryNotFoundError();
            return $result;
        }

        $result['path'] = $binaryPath;

        // Check if binary is executable
        if (!is_executable($binaryPath)) {
            $result['errors'][] = [
                'type' => self::ERROR_BINARY_NOT_EXECUTABLE,
                'message' => "oha binary found but not executable: {$binaryPath}",
                'suggestion' => $this->getExecutablePermissionSuggestion($binaryPath)
            ];
            return $result;
        }

        // Try to get version to verify binary works
        $versionResult = $this->getOhaVersion($binaryPath);
        
        if (!$versionResult['success']) {
            $result['errors'][] = [
                'type' => self::ERROR_PROCESS_START_FAILED,
                'message' => 'oha binary found but failed to execute',
                'details' => $versionResult['error'],
                'suggestion' => 'Try reinstalling oha or check if it\'s corrupted'
            ];
            return $result;
        }

        $result['available'] = true;
        $result['version'] = $versionResult['version'];

        // Add warnings for potential issues
        $this->addBinaryWarnings($result, $binaryPath);

        return $result;
    }

    /**
     * Analyze process execution error
     * 
     * @param int $exitCode Process exit code
     * @param string $output Process output
     * @param string $command Executed command
     * @return array Error analysis result
     */
    public function analyzeProcessError(int $exitCode, string $output, string $command): array
    {
        $result = [
            'type' => self::ERROR_UNKNOWN,
            'message' => 'Unknown error occurred',
            'suggestion' => 'Check the command and try again',
            'details' => [
                'exit_code' => $exitCode,
                'output' => $output,
                'command' => $command
            ]
        ];

        // Analyze based on exit code
        switch ($exitCode) {
            case 0:
                // Success - no error
                return [
                    'type' => 'success',
                    'message' => 'Process completed successfully',
                    'suggestion' => '',
                    'details' => [
                        'exit_code' => $exitCode,
                        'output' => $output,
                        'command' => $command
                    ]
                ];
                
            case 1:
                $result = $this->analyzeGeneralError($output);
                break;
                
            case 2:
                $result['type'] = self::ERROR_INVALID_ARGUMENTS;
                $result['message'] = 'Invalid command arguments';
                $result['suggestion'] = 'Check the URL and parameters for correctness';
                break;
                
            case 126:
                $result['type'] = self::ERROR_BINARY_NOT_EXECUTABLE;
                $result['message'] = 'oha binary is not executable';
                $result['suggestion'] = $this->getExecutablePermissionSuggestion('oha');
                break;
                
            case 127:
                $result['type'] = self::ERROR_BINARY_NOT_FOUND;
                $result['message'] = 'oha binary not found';
                $result['suggestion'] = $this->getOhaBinaryNotFoundError()['suggestion'];
                break;
                
            case 130:
                $result['type'] = self::ERROR_PROCESS_TIMEOUT;
                $result['message'] = 'Process was interrupted (Ctrl+C)';
                $result['suggestion'] = 'Test was stopped by user';
                break;
                
            case 137:
                $result['type'] = self::ERROR_PROCESS_CRASHED;
                $result['message'] = 'Process was killed (out of memory or timeout)';
                $result['suggestion'] = 'Reduce concurrent connections or test duration';
                break;
                
            default:
                $result = $this->analyzeOutputForErrors($output);
                break;
        }

        $result['details'] = [
            'exit_code' => $exitCode,
            'output' => $output,
            'command' => $command
        ];

        return $result;
    }

    /**
     * Handle process timeout
     * 
     * @param int $timeoutSeconds Timeout duration
     * @param string $command Command that timed out
     * @return array Timeout error details
     */
    public function handleProcessTimeout(int $timeoutSeconds, string $command): array
    {
        return [
            'type' => self::ERROR_PROCESS_TIMEOUT,
            'message' => "Process timed out after {$timeoutSeconds} seconds",
            'suggestion' => 'Consider increasing timeout or reducing test duration',
            'details' => [
                'timeout_seconds' => $timeoutSeconds,
                'command' => $command
            ]
        ];
    }

    /**
     * Validate command arguments before execution
     * 
     * @param string $command Command to validate
     * @return array Validation result with 'valid', 'errors', 'warnings'
     */
    public function validateCommand(string $command): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        // Check if command is empty
        if (empty(trim($command))) {
            $result['valid'] = false;
            $result['errors'][] = 'Command cannot be empty';
            return $result;
        }

        // Check for potentially dangerous characters
        $dangerousChars = ['|', '&', ';', '`', '$', '(', ')', '<', '>'];
        foreach ($dangerousChars as $char) {
            if (strpos($command, $char) !== false) {
                $result['warnings'][] = "Command contains potentially dangerous character: {$char}";
            }
        }

        // Check command length
        if (strlen($command) > 8192) {
            $result['valid'] = false;
            $result['errors'][] = 'Command is too long (maximum 8192 characters)';
        }

        // Validate URL in command
        if (preg_match('/https?:\/\/[^\s]+/', $command, $matches)) {
            $url = $matches[0];
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $result['warnings'][] = 'URL in command may be invalid';
            }
        }

        return $result;
    }

    /**
     * Get user-friendly error message
     * 
     * @param array $error Error details from analyzeProcessError
     * @return string User-friendly error message
     */
    public function getUserFriendlyErrorMessage(array $error): string
    {
        $message = $error['message'] ?? 'An unknown error occurred';
        
        if (!empty($error['suggestion'])) {
            $message .= "\n\nSuggestion: " . $error['suggestion'];
        }

        return $message;
    }

    /**
     * Get installation instructions for oha
     * 
     * @return string Installation instructions
     */
    public function getOhaInstallationInstructions(): string
    {
        $os = CrossPlatform::getOperatingSystem();
        
        switch ($os) {
            case CrossPlatform::OS_WINDOWS:
                return "To install oha on Windows:\n" .
                       "1. Download from: https://github.com/hatoo/oha/releases\n" .
                       "2. Extract oha.exe to a folder in your PATH\n" .
                       "3. Or install via Cargo: cargo install oha\n" .
                       "4. Or install via Scoop: scoop install oha";
                       
            case CrossPlatform::OS_MACOS:
                return "To install oha on macOS:\n" .
                       "1. Install via Homebrew: brew install oha\n" .
                       "2. Or install via Cargo: cargo install oha\n" .
                       "3. Or download from: https://github.com/hatoo/oha/releases";
                       
            case CrossPlatform::OS_LINUX:
                return "To install oha on Linux:\n" .
                       "1. Install via Cargo: cargo install oha\n" .
                       "2. Or download from: https://github.com/hatoo/oha/releases\n" .
                       "3. Or install via package manager (if available)\n" .
                       "4. Make sure ~/.cargo/bin is in your PATH";
                       
            default:
                return "To install oha:\n" .
                       "1. Install Rust and Cargo\n" .
                       "2. Run: cargo install oha\n" .
                       "3. Or download from: https://github.com/hatoo/oha/releases";
        }
    }

    /**
     * Get oha binary not found error
     */
    private function getOhaBinaryNotFoundError(): array
    {
        return [
            'type' => self::ERROR_BINARY_NOT_FOUND,
            'message' => 'oha binary not found in system PATH or common locations',
            'suggestion' => 'Install oha using the instructions provided',
            'installation_instructions' => $this->getOhaInstallationInstructions()
        ];
    }

    /**
     * Get executable permission suggestion
     */
    private function getExecutablePermissionSuggestion(string $path): string
    {
        if (CrossPlatform::isWindows()) {
            return "Check if the file is blocked by Windows security. Right-click the file, go to Properties, and unblock it if necessary.";
        } else {
            return "Run: chmod +x {$path}";
        }
    }

    /**
     * Get oha version
     */
    private function getOhaVersion(string $binaryPath): array
    {
        try {
            $command = escapeshellarg($binaryPath) . ' --version 2>&1';
            $result = CrossPlatform::executeCommand($command);
            
            if ($result['success'] && !empty($result['output'])) {
                $version = trim(implode("\n", $result['output']));
                return ['success' => true, 'version' => $version];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to get version: ' . implode("\n", $result['output'])
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception getting version: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Add warnings for binary issues
     */
    private function addBinaryWarnings(array &$result, string $binaryPath): void
    {
        // Check if binary is in a non-standard location
        $standardPaths = ['/usr/bin', '/usr/local/bin', '/bin'];
        $isStandardPath = false;
        
        foreach ($standardPaths as $standardPath) {
            if (strpos($binaryPath, $standardPath) === 0) {
                $isStandardPath = true;
                break;
            }
        }
        
        if (!$isStandardPath && !CrossPlatform::isWindows()) {
            $result['warnings'][] = 'oha binary found in non-standard location: ' . $binaryPath;
        }

        // Check file permissions
        $perms = fileperms($binaryPath);
        if ($perms !== false) {
            $octal = substr(sprintf('%o', $perms), -4);
            if (!CrossPlatform::isWindows() && $octal !== '0755' && $octal !== '0775') {
                $result['warnings'][] = "oha binary has unusual permissions: {$octal}";
            }
        }
    }

    /**
     * Analyze general error from output
     */
    private function analyzeGeneralError(string $output): array
    {
        $outputLower = strtolower($output);
        
        // Network-related errors
        if (strpos($outputLower, 'connection refused') !== false ||
            strpos($outputLower, 'connection timed out') !== false ||
            strpos($outputLower, 'no route to host') !== false) {
            return [
                'type' => self::ERROR_NETWORK_ERROR,
                'message' => 'Network connection failed',
                'suggestion' => 'Check if the target server is running and accessible'
            ];
        }
        
        // DNS resolution errors
        if (strpos($outputLower, 'name resolution failed') !== false ||
            strpos($outputLower, 'could not resolve host') !== false) {
            return [
                'type' => self::ERROR_NETWORK_ERROR,
                'message' => 'DNS resolution failed',
                'suggestion' => 'Check the URL hostname and your internet connection'
            ];
        }
        
        // SSL/TLS errors
        if (strpos($outputLower, 'ssl') !== false ||
            strpos($outputLower, 'tls') !== false ||
            strpos($outputLower, 'certificate') !== false) {
            return [
                'type' => self::ERROR_NETWORK_ERROR,
                'message' => 'SSL/TLS connection error',
                'suggestion' => 'Check if the HTTPS certificate is valid or try HTTP instead'
            ];
        }
        
        // Permission errors
        if (strpos($outputLower, 'permission denied') !== false ||
            strpos($outputLower, 'access denied') !== false) {
            return [
                'type' => self::ERROR_PERMISSION_DENIED,
                'message' => 'Permission denied',
                'suggestion' => 'Check file permissions or run with appropriate privileges'
            ];
        }
        
        return [
            'type' => self::ERROR_UNKNOWN,
            'message' => 'Test execution failed',
            'suggestion' => 'Check the error output for more details'
        ];
    }

    /**
     * Analyze output for specific error patterns
     */
    private function analyzeOutputForErrors(string $output): array
    {
        // Try to extract meaningful error information from output
        $lines = explode("\n", $output);
        $errorLines = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Look for error indicators
            if (preg_match('/error|failed|exception|panic/i', $line)) {
                $errorLines[] = $line;
            }
        }
        
        if (!empty($errorLines)) {
            return [
                'type' => self::ERROR_UNKNOWN,
                'message' => 'Process failed with errors',
                'suggestion' => 'Check the error details below',
                'error_details' => implode("\n", $errorLines)
            ];
        }
        
        return [
            'type' => self::ERROR_UNKNOWN,
            'message' => 'Process failed with unknown error',
            'suggestion' => 'Check the full output for more information'
        ];
    }
}