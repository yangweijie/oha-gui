<?php

declare(strict_types=1);

namespace OhaGui\Utils;

/**
 * User-friendly message handling utility
 * Provides comprehensive error messages and user guidance
 */
class UserMessages
{
    /**
     * Get user-friendly error message for common issues
     * 
     * @param string $errorType
     * @param string $details
     * @return string
     */
    public static function getErrorMessage(string $errorType, string $details = ''): string
    {
        $messages = [
            'oha_not_found' => [
                'title' => 'OHA Binary Not Found',
                'message' => 'The oha command-line tool is required but not found on your system.',
                'solutions' => [
                    'Install oha using Cargo: cargo install oha',
                    'Download from GitHub: https://github.com/hatoo/oha/releases',
                    'On macOS with Homebrew: brew install oha',
                    'On Ubuntu/Debian: Check if oha is available in repositories',
                    'Ensure oha is in your system PATH or place it in the bin/ directory'
                ]
            ],
            'libui_not_found' => [
                'title' => 'LibUI Library Not Found',
                'message' => 'The libui GUI library is required but not properly configured.',
                'solutions' => [
                    'Run: composer install',
                    'Ensure kingbes/libui package is installed',
                    'Check that FFI extension is enabled in php.ini',
                    'Verify libui.dylib (macOS) or libui.dll (Windows) is present'
                ]
            ],
            'ffi_not_enabled' => [
                'title' => 'FFI Extension Not Enabled',
                'message' => 'The PHP FFI extension is required for GUI functionality.',
                'solutions' => [
                    'Enable FFI in php.ini: extension=ffi',
                    'Restart your web server or PHP process',
                    'Check PHP configuration with: php -m | grep ffi',
                    'On some systems: sudo apt-get install php-ffi (Linux)',
                    'Ensure you are using PHP 7.4 or higher'
                ]
            ],
            'config_save_failed' => [
                'title' => 'Configuration Save Failed',
                'message' => 'Unable to save the test configuration.',
                'solutions' => [
                    'Check file permissions in the configuration directory',
                    'Ensure sufficient disk space is available',
                    'Verify the configuration name contains only valid characters',
                    'Try using a different configuration name'
                ]
            ],
            'config_load_failed' => [
                'title' => 'Configuration Load Failed',
                'message' => 'Unable to load the selected configuration.',
                'solutions' => [
                    'Check if the configuration file exists',
                    'Verify the configuration file is not corrupted',
                    'Try refreshing the configuration list',
                    'Create a new configuration if the old one is damaged'
                ]
            ],
            'test_execution_failed' => [
                'title' => 'Test Execution Failed',
                'message' => 'The HTTP load test could not be executed.',
                'solutions' => [
                    'Verify the target URL is accessible',
                    'Check your internet connection',
                    'Ensure the oha binary is working: oha --version',
                    'Try with a simpler URL like https://httpbin.org/get',
                    'Check if the server supports the HTTP method you selected'
                ]
            ],
            'invalid_url' => [
                'title' => 'Invalid URL',
                'message' => 'The provided URL is not valid.',
                'solutions' => [
                    'Ensure URL starts with http:// or https://',
                    'Check for typos in the URL',
                    'Example valid URLs:',
                    '  - https://httpbin.org/get',
                    '  - http://localhost:8080/api/test',
                    '  - https://api.example.com/v1/users'
                ]
            ],
            'permission_denied' => [
                'title' => 'Permission Denied',
                'message' => 'Insufficient permissions to perform this operation.',
                'solutions' => [
                    'Check file and directory permissions',
                    'Ensure you have write access to the configuration directory',
                    'On Unix systems, try: chmod 755 ~/.config/oha-gui',
                    'Run the application with appropriate user privileges'
                ]
            ]
        ];

        if (!isset($messages[$errorType])) {
            return "An unknown error occurred" . ($details ? ": $details" : ".");
        }

        $error = $messages[$errorType];
        $message = $error['title'] . "\n\n";
        $message .= $error['message'];
        
        if ($details) {
            $message .= "\n\nDetails: " . $details;
        }
        
        $message .= "\n\nSuggested solutions:\n";
        foreach ($error['solutions'] as $i => $solution) {
            $message .= ($i + 1) . ". " . $solution . "\n";
        }

        return $message;
    }

    /**
     * Get success message for common operations
     * 
     * @param string $operation
     * @param string $details
     * @return string
     */
    public static function getSuccessMessage(string $operation, string $details = ''): string
    {
        $messages = [
            'config_saved' => 'Configuration saved successfully!',
            'config_loaded' => 'Configuration loaded successfully!',
            'config_deleted' => 'Configuration deleted successfully!',
            'test_completed' => 'Load test completed successfully!',
            'app_started' => 'OHA GUI Tool started successfully!',
            'requirements_ok' => 'All system requirements are satisfied!'
        ];

        $message = $messages[$operation] ?? 'Operation completed successfully!';
        
        if ($details) {
            $message .= "\n" . $details;
        }

        return $message;
    }

    /**
     * Get warning message for common situations
     * 
     * @param string $warningType
     * @param string $details
     * @return string
     */
    public static function getWarningMessage(string $warningType, string $details = ''): string
    {
        $messages = [
            'oha_not_in_path' => [
                'message' => 'OHA binary not found in system PATH.',
                'note' => 'Tests can still be run if oha is in the bin/ directory.'
            ],
            'config_overwrite' => [
                'message' => 'A configuration with this name already exists.',
                'note' => 'Saving will overwrite the existing configuration.'
            ],
            'long_test_duration' => [
                'message' => 'Test duration is quite long.',
                'note' => 'Consider using a shorter duration for initial testing.'
            ],
            'high_concurrency' => [
                'message' => 'High concurrency level detected.',
                'note' => 'This may put significant load on the target server.'
            ]
        ];

        if (!isset($messages[$warningType])) {
            return "Warning: " . ($details ?: "Please review your settings.");
        }

        $warning = $messages[$warningType];
        $message = "Warning: " . $warning['message'];
        
        if ($details) {
            $message .= "\nDetails: " . $details;
        }
        
        $message .= "\nNote: " . $warning['note'];

        return $message;
    }

    /**
     * Get help text for specific features
     * 
     * @param string $feature
     * @return string
     */
    public static function getHelpText(string $feature): string
    {
        $helpTexts = [
            'url_field' => 'Enter the URL you want to test. Must start with http:// or https://. Example: https://httpbin.org/get',
            'method_field' => 'Select the HTTP method. GET is most common for testing, POST/PUT for APIs with data.',
            'connections_field' => 'Number of concurrent connections. Start with 1-10 for testing, increase gradually.',
            'duration_field' => 'How long to run the test in seconds. Start with 5-10 seconds for initial testing.',
            'timeout_field' => 'Request timeout in seconds. 30 seconds is usually sufficient for most APIs.',
            'headers_field' => 'HTTP headers in "Key: Value" format, one per line. Example: Content-Type: application/json',
            'body_field' => 'Request body for POST/PUT requests. Use JSON format for APIs. Example: {"key": "value"}',
            'configuration_dropdown' => 'Select a saved configuration to load its settings, or create a new one.',
            'management_button' => 'Click to open configuration management where you can add, edit, or delete saved configurations.',
            'start_button' => 'Begin the load test with current settings. Ensure all required fields are filled.',
            'stop_button' => 'Stop the currently running test. Results will show partial data.',
            'results_area' => 'Test results will appear here, including requests per second, success rate, and detailed metrics.'
        ];

        return $helpTexts[$feature] ?? 'No help available for this feature.';
    }

    /**
     * Format validation errors for display
     * 
     * @param array $errors
     * @return string
     */
    public static function formatValidationErrors(array $errors): string
    {
        if (empty($errors)) {
            return '';
        }

        $message = "Please fix the following issues:\n\n";
        foreach ($errors as $field => $error) {
            $message .= "• " . ucfirst($field) . ": " . $error . "\n";
        }

        return $message;
    }

    /**
     * Get keyboard shortcuts help
     * 
     * @return string
     */
    public static function getKeyboardShortcuts(): string
    {
        return "Keyboard Shortcuts:\n\n" .
               "Ctrl+N (Cmd+N on Mac) - New configuration\n" .
               "Ctrl+S (Cmd+S on Mac) - Save configuration\n" .
               "Ctrl+O (Cmd+O on Mac) - Open configuration manager\n" .
               "F5 - Start test\n" .
               "Escape - Stop test\n" .
               "Ctrl+Q (Cmd+Q on Mac) - Quit application\n" .
               "F1 - Show help\n\n" .
               "Note: Some shortcuts may not be available depending on the GUI library capabilities.";
    }
}