<?php

namespace OhaGui\Utils;

/**
 * User guidance and error message utility class
 * 
 * Provides comprehensive error messages and user guidance for common issues
 */
class UserGuidance
{
    /**
     * Get user-friendly error messages with suggestions
     * 
     * @param string $errorType
     * @param string $details
     * @return array
     */
    public static function getErrorGuidance(string $errorType, string $details = ''): array
    {
        $guidance = [
            'title' => 'Error',
            'message' => 'An error occurred.',
            'suggestions' => []
        ];

        switch ($errorType) {
            case 'oha_not_found':
                $guidance['title'] = 'OHA Binary Not Found';
                $guidance['message'] = 'The oha binary was not found in the bin directory.';
                $guidance['suggestions'] = [
                    'Download the oha binary for your platform from https://github.com/hatoo/oha/releases',
                    'Place the binary in the "bin" directory of this application',
                    'On Windows, use "oha.exe"; on macOS/Linux, use "oha"',
                    'Ensure the binary has execute permissions (chmod +x oha on Unix-like systems)'
                ];
                break;

            case 'oha_execution_failed':
                $guidance['title'] = 'Test Execution Failed';
                $guidance['message'] = 'Failed to execute the oha command. ' . $details;
                $guidance['suggestions'] = [
                    'Check that the target URL is accessible',
                    'Verify your internet connection',
                    'Ensure the oha binary is not corrupted',
                    'Try reducing the number of concurrent connections',
                    'Check if the target server is blocking requests'
                ];
                break;

            case 'invalid_url':
                $guidance['title'] = 'Invalid URL';
                $guidance['message'] = 'The provided URL is not valid. ' . $details;
                $guidance['suggestions'] = [
                    'Ensure the URL starts with http:// or https://',
                    'Check for typos in the URL',
                    'Verify the domain name is correct',
                    'Test the URL in a web browser first'
                ];
                break;

            case 'config_save_failed':
                $guidance['title'] = 'Configuration Save Failed';
                $guidance['message'] = 'Failed to save the configuration. ' . $details;
                $guidance['suggestions'] = [
                    'Check that you have write permissions to the configuration directory',
                    'Ensure there is enough disk space available',
                    'Try using a different configuration name',
                    'Check if the configuration directory exists and is accessible'
                ];
                break;

            case 'config_load_failed':
                $guidance['title'] = 'Configuration Load Failed';
                $guidance['message'] = 'Failed to load the configuration. ' . $details;
                $guidance['suggestions'] = [
                    'Check that the configuration file exists',
                    'Verify the configuration file is not corrupted',
                    'Ensure you have read permissions to the configuration file',
                    'Try creating a new configuration instead'
                ];
                break;

            case 'validation_failed':
                $guidance['title'] = 'Validation Error';
                $guidance['message'] = 'The configuration contains invalid values. ' . $details;
                $guidance['suggestions'] = [
                    'Check that all required fields are filled',
                    'Ensure numeric values are within valid ranges',
                    'Verify the URL format is correct',
                    'Check that headers are in the correct format (key: value)'
                ];
                break;

            case 'network_error':
                $guidance['title'] = 'Network Error';
                $guidance['message'] = 'A network error occurred during testing. ' . $details;
                $guidance['suggestions'] = [
                    'Check your internet connection',
                    'Verify the target server is accessible',
                    'Try reducing the timeout value',
                    'Check if a firewall is blocking the connection',
                    'Ensure the target server can handle the load'
                ];
                break;

            case 'permission_error':
                $guidance['title'] = 'Permission Error';
                $guidance['message'] = 'Permission denied. ' . $details;
                $guidance['suggestions'] = [
                    'Run the application with appropriate permissions',
                    'Check file and directory permissions',
                    'Ensure the oha binary has execute permissions',
                    'Try running as administrator (Windows) or with sudo (Unix-like systems)'
                ];
                break;

            case 'libui_error':
                $guidance['title'] = 'GUI Error';
                $guidance['message'] = 'A GUI-related error occurred. ' . $details;
                $guidance['suggestions'] = [
                    'Ensure the kingbes/libui package is properly installed',
                    'Check that your system supports GUI applications',
                    'Try restarting the application',
                    'Verify your display settings and drivers'
                ];
                break;

            default:
                $guidance['title'] = 'Unknown Error';
                $guidance['message'] = 'An unexpected error occurred. ' . $details;
                $guidance['suggestions'] = [
                    'Try restarting the application',
                    'Check the application logs for more details',
                    'Ensure all dependencies are properly installed',
                    'Contact support if the problem persists'
                ];
                break;
        }

        return $guidance;
    }

    /**
     * Get keyboard shortcuts help text
     * 
     * @return string
     */
    public static function getKeyboardShortcutsHelp(): string
    {
        return "Keyboard Shortcuts:\n\n" .
               "Ctrl+N    - New configuration\n" .
               "Ctrl+O    - Load configuration\n" .
               "Ctrl+S    - Save configuration\n" .
               "F5        - Start test\n" .
               "Esc       - Stop test\n" .
               "Ctrl+Q    - Quit application\n" .
               "F1        - Show this help\n";
    }

    /**
     * Get application usage tips
     * 
     * @return string
     */
    public static function getUsageTips(): string
    {
        return "Usage Tips:\n\n" .
               "• Start with a small number of concurrent connections (1-10) for initial testing\n" .
               "• Use shorter durations (10-30 seconds) for quick tests\n" .
               "• Save configurations you use frequently\n" .
               "• Monitor the target server's response during testing\n" .
               "• Use appropriate headers (User-Agent, Content-Type) for realistic testing\n" .
               "• Test with different HTTP methods to simulate real usage patterns\n" .
               "• Always respect the target server's rate limits and terms of service\n";
    }

    /**
     * Get troubleshooting guide
     * 
     * @return string
     */
    public static function getTroubleshootingGuide(): string
    {
        return "Troubleshooting Guide:\n\n" .
               "1. OHA Binary Issues:\n" .
               "   • Download from https://github.com/hatoo/oha/releases\n" .
               "   • Place in the 'bin' directory\n" .
               "   • Ensure execute permissions\n\n" .
               "2. Connection Issues:\n" .
               "   • Check internet connectivity\n" .
               "   • Verify target URL accessibility\n" .
               "   • Check firewall settings\n\n" .
               "3. Configuration Issues:\n" .
               "   • Validate all input fields\n" .
               "   • Check file permissions\n" .
               "   • Ensure adequate disk space\n\n" .
               "4. Performance Issues:\n" .
               "   • Reduce concurrent connections\n" .
               "   • Shorten test duration\n" .
               "   • Check system resources\n";
    }

    /**
     * Format error message for display
     * 
     * @param array $guidance
     * @return string
     */
    public static function formatErrorMessage(array $guidance): string
    {
        $message = $guidance['message'];
        
        if (!empty($guidance['suggestions'])) {
            $message .= "\n\nSuggestions:\n";
            foreach ($guidance['suggestions'] as $suggestion) {
                $message .= "• " . $suggestion . "\n";
            }
        }
        
        return $message;
    }

    /**
     * Get platform-specific installation instructions
     * 
     * @return string
     */
    public static function getInstallationInstructions(): string
    {
        $os = CrossPlatform::getOperatingSystem();
        
        $instructions = "Installation Instructions:\n\n";
        
        switch ($os) {
            case CrossPlatform::OS_WINDOWS:
                $instructions .= "Windows:\n";
                $instructions .= "1. Download oha.exe from https://github.com/hatoo/oha/releases\n";
                $instructions .= "2. Place oha.exe in the 'bin' directory\n";
                $instructions .= "3. Run the application using run.bat or 'php main.php'\n";
                break;
                
            case CrossPlatform::OS_MACOS:
                $instructions .= "macOS:\n";
                $instructions .= "1. Install using Homebrew: 'brew install oha'\n";
                $instructions .= "   OR download from https://github.com/hatoo/oha/releases\n";
                $instructions .= "2. Copy the oha binary to the 'bin' directory\n";
                $instructions .= "3. Make executable: 'chmod +x bin/oha'\n";
                $instructions .= "4. Run: './run.sh' or 'php main.php'\n";
                break;
                
            case CrossPlatform::OS_LINUX:
                $instructions .= "Linux:\n";
                $instructions .= "1. Download from https://github.com/hatoo/oha/releases\n";
                $instructions .= "   OR compile from source if available for your architecture\n";
                $instructions .= "2. Copy the oha binary to the 'bin' directory\n";
                $instructions .= "3. Make executable: 'chmod +x bin/oha'\n";
                $instructions .= "4. Run: './run.sh' or 'php main.php'\n";
                break;
                
            default:
                $instructions .= "Unknown Platform:\n";
                $instructions .= "1. Download oha binary for your platform\n";
                $instructions .= "2. Place in the 'bin' directory\n";
                $instructions .= "3. Ensure execute permissions\n";
                $instructions .= "4. Run: 'php main.php'\n";
                break;
        }
        
        return $instructions;
    }
}