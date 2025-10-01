<?php

namespace OhaGui\Core;

use InvalidArgumentException;
use OhaGui\Models\TestConfiguration;
use OhaGui\Utils\CrossPlatform;
use RuntimeException;
use Psl\Filesystem;
use Psl\Shell;

/**
 * OhaCommandBuilder - Builds oha command strings from TestConfiguration objects
 * 
 * This class is responsible for converting TestConfiguration objects into properly
 * formatted oha command strings with cross-platform binary path resolution and
 * proper argument escaping.
 */
class OhaCommandBuilder
{
    /**
     * Build oha command from TestConfiguration
     * 
     * @param TestConfiguration $config The test configuration
     * @return string The complete oha command string
     * @throws InvalidArgumentException If configuration is invalid
     * @throws RuntimeException If oha binary is not available
     */
    public function buildCommand(TestConfiguration $config): string
    {
        // Validate only the fields needed for command building (skip name validation)
        $validationErrors = $this->validateForCommandBuilding($config);
        if (!empty($validationErrors)) {
            throw new InvalidArgumentException('Invalid configuration: ' . implode(', ', $validationErrors));
        }

        // Validate command security
        $this->validateCommandSecurity($config);

        $command = [];
        
        // Add oha binary path (this will throw if not found)
        $binaryPath = $this->getOhaBinaryPath();
        $command[] = $this->escapeArgument($binaryPath);
        
        // Add concurrent connections parameter
        $command[] = '-c';
        $command[] = (string)$config->concurrentConnections;
        
        // Add duration parameter
        $command[] = '-z';
        $command[] = $config->duration . 's';
        
        // Add timeout parameter
        $command[] = '-t';
        $command[] = $config->timeout . 's';
        
        // Add HTTP method parameter
        $command[] = '-m';
        $command[] = strtoupper($config->method);
        
        // Disable TUI for programmatic output parsing
        $command[] = '--no-tui';
        
        // Add request headers
        foreach ($config->headers as $key => $value) {
            $command[] = '-H';
            $command[] = $this->escapeArgument($key . ': ' . $value);
        }
        
        // Add request body if provided
        if (!empty($config->body)) {
            $command[] = '-d';
            $command[] = $this->escapeArgument($config->body);
        }
        
        // Add target URL (must be last)
        $command[] = $this->escapeArgument($config->url);
        
        return implode(' ', $command);
    }
    
    /**
     * Get the appropriate oha binary path for the current platform
     * 
     * @return string The oha binary path
     * @throws RuntimeException If oha binary cannot be found
     */
    private function getOhaBinaryPath(): string
    {
        $binaryName = CrossPlatform::isWindows() ? 'oha.exe' : 'oha';
        
        // Only check local bin directory
        $localBinPath = getcwd() . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $binaryName;
        if (\Psl\Filesystem\exists($localBinPath) && \Psl\Filesystem\is_executable($localBinPath)) {
            return $localBinPath;
        }
        
        // If not found in local bin directory, throw an exception
        throw new RuntimeException(
            'oha binary not found in bin directory. Please place oha binary in the bin directory. ' .
            'Visit https://github.com/hatoo/oha for installation instructions.'
        );
    }
    
    
    
    /**
     * Escape command line argument to prevent injection and handle special characters
     * 
     * @param string $arg The argument to escape
     * @return string The escaped argument
     */
    private function escapeArgument(string $arg): string
    {
        if (CrossPlatform::isWindows()) {
            // Windows command line escaping
            // Escape double quotes and wrap in double quotes
            $escaped = str_replace('"', '""', $arg);
            return '"' . $escaped . '"';
        } else {
            // Unix-like systems: use single quotes and escape single quotes
            $escaped = str_replace("'", "'\"'\"'", $arg);
            return "'" . $escaped . "'";
        }
    }
    
    /**
     * Validate that oha binary is available and executable
     * 
     * @return bool True if oha is available, false otherwise
     */
    public function isOhaAvailable(): bool
    {
        try {
            $binaryPath = $this->getOhaBinaryPath();
            
            // Try to execute oha --version to verify it's working
            $result = \Psl\Shell\execute($binaryPath, ['--version']);
            
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get detailed error information about oha availability
     * 
     * @return array Array with 'available' (bool) and 'error' (string) keys
     */
    public function getOhaAvailabilityInfo(): array
    {
        try {
            $binaryPath = $this->getOhaBinaryPath();
            
            // Check if file exists
            if (!\Psl\Filesystem\exists($binaryPath)) {
                return [
                    'available' => false,
                    'error' => 'oha binary not found at: ' . $binaryPath
                ];
            }
            
            // Check if file is executable
            if (!\Psl\Filesystem\is_executable($binaryPath)) {
                return [
                    'available' => false,
                    'error' => 'oha binary is not executable: ' . $binaryPath . '. Check file permissions.'
                ];
            }
            
            // Try to execute oha --version to verify it's working
            $output = \Psl\Shell\execute($binaryPath, ['--version']);
            
            return [
                'available' => true,
                'error' => '',
                'path' => $binaryPath,
                'version' => trim($output)
            ];
            
        } catch (\Exception $e) {
            return [
                'available' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get oha version information
     * 
     * @return string|null The oha version string, or null if not available
     */
    public function getOhaVersion(): ?string
    {
        try {
            $binaryPath = $this->getOhaBinaryPath();
            
            $output = \Psl\Shell\execute($binaryPath, ['--version']);
            
            return trim($output);
        } catch (\Exception $e) {
            // Ignore exceptions and return null
        }
        
        return null;
    }

    /**
     * Validate command arguments for potential security issues
     * 
     * @param TestConfiguration $config The configuration to validate
     * @throws InvalidArgumentException If arguments contain potential security issues
     */
    private function validateCommandSecurity(TestConfiguration $config): void
    {
        // Check for command injection attempts in URL
        // Note: We allow & in URLs as it's a valid query parameter separator
        $dangerousUrlPatterns = [
            '/[;|`$(){}[\]]/',  // Shell metacharacters (excluding &)
            '/\s*(rm|del|format|shutdown|reboot)\s+/i',  // Dangerous commands
            '/\s*>\s*/',  // Output redirection
            '/\s*<\s*/',  // Input redirection
        ];
        
        foreach ($dangerousUrlPatterns as $pattern) {
            if (preg_match($pattern, $config->url)) {
                throw new InvalidArgumentException('URL contains potentially dangerous characters or commands');
            }
        }
        
        // Check headers for injection attempts
        $dangerousPatterns = [
            '/[;&|`$(){}[\]]/',  // Shell metacharacters
            '/\s*(rm|del|format|shutdown|reboot)\s+/i',  // Dangerous commands
            '/\s*>\s*/',  // Output redirection
            '/\s*<\s*/',  // Input redirection
        ];
        
        foreach ($config->headers as $key => $value) {
            foreach ($dangerousPatterns as $pattern) {
                if (preg_match($pattern, $key) || preg_match($pattern, $value)) {
                    throw new InvalidArgumentException('Header contains potentially dangerous characters or commands');
                }
            }
        }
        
        // Check body for injection attempts (less strict as it's data)
        if (!empty($config->body)) {
            if (preg_match('/[;&|`$()]/', $config->body)) {
                // Only warn about shell metacharacters in body, don't block
                error_log('Warning: Request body contains shell metacharacters');
            }
        }
    }
    
    /**
     * Validate configuration fields needed for command building
     * This is a subset of the full validation that excludes name validation
     * 
     * @param TestConfiguration $config The configuration to validate
     * @return array Array of validation errors
     */
    private function validateForCommandBuilding(TestConfiguration $config): array
    {
        $errors = [];

        // Validate URL format
        if (empty($config->url)) {
            $errors[] = 'URL is required';
        } elseif (!filter_var($config->url, FILTER_VALIDATE_URL)) {
            $errors[] = 'URL format is invalid';
        }

        // Validate HTTP method
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        if (!in_array(strtoupper($config->method), $validMethods)) {
            $errors[] = 'HTTP method must be one of: ' . implode(', ', $validMethods);
        }

        // Validate concurrent connections
        if ($config->concurrentConnections < 1 || $config->concurrentConnections > 1000) {
            $errors[] = 'Concurrent connections must be between 1 and 1000';
        }

        // Validate duration
        if ($config->duration < 1 || $config->duration > 3600) {
            $errors[] = 'Duration must be between 1 and 3600 seconds';
        }

        // Validate timeout
        if ($config->timeout < 1 || $config->timeout > 300) {
            $errors[] = 'Timeout must be between 1 and 300 seconds';
        }

        // Validate headers format
        if (!is_array($config->headers)) {
            $errors[] = 'Headers must be an array';
        } else {
            foreach ($config->headers as $key => $value) {
                if (!is_string($key) || !is_string($value)) {
                    $errors[] = 'Headers must be key-value pairs of strings';
                    break;
                }
            }
        }

        // Validate request body for JSON format if method supports body
        $methodsWithBody = ['POST', 'PUT', 'PATCH'];
        if (in_array(strtoupper($config->method), $methodsWithBody) && !empty($config->body)) {
            if (!$this->isValidJson($config->body) && !$this->isValidFormData($config->body)) {
                $errors[] = 'Request body must be valid JSON or form data';
            }
        }

        return $errors;
    }
    
    /**
     * Check if string is valid JSON
     */
    private function isValidJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Check if string is valid form data format
     */
    private function isValidFormData(string $string): bool
    {
        // Simple check for form data format (key=value&key=value)
        return preg_match('/^[^=&]+(=[^&]*)?(&[^=&]+(=[^&]*)?)*$/', $string) === 1;
    }
}