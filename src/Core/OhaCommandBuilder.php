<?php

namespace OhaGui\Core;

use OhaGui\Models\TestConfiguration;
use OhaGui\Utils\CrossPlatform;
use InvalidArgumentException;

/**
 * OHA Command Builder
 * Converts TestConfiguration objects into executable oha commands
 */
class OhaCommandBuilder
{
    private bool $testMode = false;

    /**
     * Enable test mode (allows building commands without oha binary)
     */
    public function enableTestMode(): void
    {
        $this->testMode = true;
    }

    /**
     * Disable test mode
     */
    public function disableTestMode(): void
    {
        $this->testMode = false;
    }

    /**
     * Build oha command from TestConfiguration
     * 
     * @param TestConfiguration $config The test configuration
     * @return string The complete oha command
     * @throws InvalidArgumentException If configuration is invalid or oha binary not found
     */
    public function buildCommand(TestConfiguration $config): string
    {
        // Validate configuration first
        $errors = $config->validate();
        if (!empty($errors)) {
            throw new InvalidArgumentException('Invalid configuration: ' . implode(', ', $errors));
        }

        // Get oha binary path
        $binaryPath = $this->getOhaBinaryPath();
        if (!$binaryPath && !$this->testMode) {
            throw new InvalidArgumentException('oha binary not found. Please install oha and ensure it\'s in your PATH.');
        }

        // Use 'oha' as default in test mode
        if (!$binaryPath && $this->testMode) {
            $binaryPath = 'oha';
        }

        // Build command components
        $command = [];
        $command[] = escapeshellarg($binaryPath);
        
        // Add concurrent connections parameter
        $command[] = '-c ' . (int)$config->concurrentConnections;
        
        // Add duration parameter
        $command[] = '-z ' . (int)$config->duration . 's';
        
        // Add timeout parameter
        $command[] = '-t ' . (int)$config->timeout . 's';
        
        // Add HTTP method parameter
        $command[] = '-m ' . escapeshellarg(strtoupper($config->method));
        
        // Disable TUI for programmatic output
        $command[] = '--no-tui';
        
        // Add request headers
        foreach ($config->headers as $key => $value) {
            $headerString = trim($key) . ': ' . trim($value);
            $command[] = '-H ' . escapeshellarg($headerString);
        }
        
        // Add request body if provided and method supports it
        $methodsWithBody = ['POST', 'PUT', 'PATCH'];
        if (in_array(strtoupper($config->method), $methodsWithBody) && !empty(trim($config->body))) {
            $command[] = '-d ' . escapeshellarg($config->body);
        }
        
        // Add target URL (must be last)
        $command[] = escapeshellarg($config->url);
        
        return implode(' ', $command);
    }

    /**
     * Get the path to the oha binary
     * 
     * @return string|null The path to oha binary or null if not found
     */
    private function getOhaBinaryPath(): ?string
    {
        return CrossPlatform::findOhaBinaryPath();
    }

    /**
     * Escape a command argument for shell execution
     * 
     * @param string $arg The argument to escape
     * @return string The escaped argument
     */
    private function escapeArgument(string $arg): string
    {
        return escapeshellarg($arg);
    }

    /**
     * Validate that oha binary is available and working
     * 
     * @return bool True if oha is available, false otherwise
     */
    public function isOhaAvailable(): bool
    {
        return CrossPlatform::isOhaAvailable();
    }

    /**
     * Get oha version information
     * 
     * @return string|null Version string or null if not available
     */
    public function getOhaVersion(): ?string
    {
        $binaryPath = $this->getOhaBinaryPath();
        if (!$binaryPath) {
            return null;
        }

        $command = escapeshellarg($binaryPath) . ' --version';
        $result = CrossPlatform::executeCommand($command);
        
        if ($result['success'] && !empty($result['output'])) {
            return trim(implode("\n", $result['output']));
        }
        
        return null;
    }

    /**
     * Build a test command to verify oha functionality
     * This creates a minimal command for testing purposes
     * 
     * @param string $testUrl Optional test URL (defaults to httpbin.org)
     * @return string Test command
     */
    public function buildTestCommand(string $testUrl = 'https://httpbin.org/get'): string
    {
        $binaryPath = $this->getOhaBinaryPath();
        if (!$binaryPath && !$this->testMode) {
            throw new InvalidArgumentException('oha binary not found');
        }

        // Use 'oha' as default in test mode
        if (!$binaryPath && $this->testMode) {
            $binaryPath = 'oha';
        }

        return escapeshellarg($binaryPath) . ' -c 1 -z 1s --no-tui ' . escapeshellarg($testUrl);
    }
}