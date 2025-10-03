#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * OHA GUI Tool - Main Entry Point
 * 
 * A cross-platform GUI application for HTTP load testing using the oha command-line tool.
 * Built with PHP and kingbes/libui library.
 * 
 * @author yangweijie <917647288@qq.com>
 * @version 1.0.0
 */

// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set memory limit for GUI applications
ini_set('memory_limit', '256M');

// Define application constants
define('APP_NAME', 'OHA GUI Tool');
define('APP_VERSION', '1.0.0');
define('APP_ROOT', __DIR__);

/**
 * Bootstrap the application
 */
function bootstrap(): void
{
    // Check PHP version requirement
    if (version_compare(PHP_VERSION, '8.0.0', '<')) {
        fwrite(STDERR, "Error: PHP 8.0.0 or higher is required. Current version: " . PHP_VERSION . "\n");
        exit(1);
    }

    // Check if running in CLI mode
    if (php_sapi_name() !== 'cli') {
        fwrite(STDERR, "Error: This application must be run from the command line.\n");
        exit(1);
    }

    // Load Composer autoloader
    $autoloadPaths = [
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
    ];

    $autoloaderFound = false;
    foreach ($autoloadPaths as $autoloadPath) {
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
            $autoloaderFound = true;
            break;
        }
    }

    if (!$autoloaderFound) {
        fwrite(STDERR, "Error: Composer autoloader not found. Please run 'composer install'.\n");
        exit(1);
    }
}

/**
 * Check system requirements
 */
function checkSystemRequirements(): void
{
    // Check if FFI extension is loaded
    if (!extension_loaded('ffi')) {
        $errorMessage = \OhaGui\Utils\UserMessages::getErrorMessage('ffi_not_enabled');
        fwrite(STDERR, $errorMessage . "\n");
        exit(1);
    }

    // Check if libui library is available
    if (!class_exists('Kingbes\\Libui\\Base')) {
        $errorMessage = \OhaGui\Utils\UserMessages::getErrorMessage('libui_not_found');
        fwrite(STDERR, $errorMessage . "\n");
        exit(1);
    }
    
    echo "✓ libui library (Kingbes\\Libui\\Base) is available\n";

    // Try to load the libui library
    try {
        $ffi = \Kingbes\Libui\Base::ffi();
        echo "✓ libui library loaded successfully\n";
    } catch (\Exception $e) {
        $errorMessage = \OhaGui\Utils\UserMessages::getErrorMessage('libui_load_failed', $e->getMessage());
        fwrite(STDERR, $errorMessage . "\n");
        exit(1);
    } catch (\Throwable $e) {
        $errorMessage = \OhaGui\Utils\UserMessages::getErrorMessage('libui_load_failed', $e->getMessage());
        fwrite(STDERR, $errorMessage . "\n");
        exit(1);
    }

    // Check if oha binary is available
    $ohaBinary = \OhaGui\Utils\CrossPlatform::getOhaBinaryPath();
    if (!$ohaBinary || !is_executable($ohaBinary)) {
        $warningMessage = \OhaGui\Utils\UserMessages::getWarningMessage('oha_not_in_path');
        fwrite(STDERR, $warningMessage . "\n");
        echo "Attempting to download oha binary...\n";
        
        // Try to download the binary
        $downloader = new \OhaGui\Utils\BinaryDownloader();
        $downloadedBinary = $downloader->downloadBinary();
        
        if ($downloadedBinary !== null) {
            echo "✓ oha binary downloaded successfully to: $downloadedBinary\n";
            $ohaBinary = $downloadedBinary;
        } else {
            echo "The application will start but tests cannot be executed without oha.\n";
        }
    } else {
        echo "✓ oha binary found at: $ohaBinary\n";
    }
}

/**
 * Display application information
 */
function displayInfo(): void
{
    echo APP_NAME . " v" . APP_VERSION . "\n";
    echo "Cross-platform HTTP load testing GUI\n";
    echo "Built with PHP " . PHP_VERSION . " and kingbes/libui\n";
    echo "Starting application...\n\n";
}

/**
 * Handle command line arguments
 */
function handleArguments(array $argv): void
{
    if (count($argv) > 1) {
        $arg = $argv[1];
        
        switch ($arg) {
            case '--version':
            case '-v':
                echo APP_VERSION . "\n";
                exit(0);
                
            case '--help':
            case '-h':
                displayHelp();
                exit(0);
                
            case '--check':
                echo "Checking system requirements...\n";
                // Bootstrap first to load autoloader
                bootstrap();
                checkSystemRequirements();
                echo "All requirements satisfied.\n";
                exit(0);
                
            default:
                fwrite(STDERR, "Unknown argument: $arg\n");
                fwrite(STDERR, "Use --help for usage information.\n");
                exit(1);
        }
    }
}

/**
 * Display help information
 */
function displayHelp(): void
{
    echo APP_NAME . " v" . APP_VERSION . "\n\n";
    echo "Usage: php main.php [options]\n\n";
    echo "Options:\n";
    echo "  --help, -h     Show this help message\n";
    echo "  --version, -v  Show version information\n";
    echo "  --check        Check system requirements\n";
    echo "\n";
    echo "Examples:\n";
    echo "  php main.php           Start the GUI application\n";
    echo "  php main.php --check   Check if all requirements are met\n";
    echo "\n";
    echo "Requirements:\n";
    echo "  - PHP 8.0.0 or higher\n";
    echo "  - FFI extension enabled\n";
    echo "  - libui library installed\n";
    echo "  - oha binary in PATH (for running tests)\n";
}

/**
 * Setup signal handlers for graceful shutdown
 */
function setupSignalHandlers(): void
{
    if (function_exists('pcntl_signal')) {
        // Handle SIGINT (Ctrl+C)
        pcntl_signal(SIGINT, function($signo) {
            echo "\nReceived interrupt signal. Shutting down gracefully...\n";
            exit(0);
        });
        
        // Handle SIGTERM
        pcntl_signal(SIGTERM, function($signo) {
            echo "\nReceived termination signal. Shutting down gracefully...\n";
            exit(0);
        });
    }
}

/**
 * Main application entry point
 */
function main(array $argv): void
{
    try {
        // Handle command line arguments first
        handleArguments($argv);
        
        // Bootstrap the application
        bootstrap();
        
        // Display application info
        displayInfo();
        
        // Check system requirements
        checkSystemRequirements();
        
        // Setup signal handlers
        setupSignalHandlers();
        
        // Create and run the application
        $app = new \OhaGui\App\OhaGuiApp();
        $app->run();
        
    } catch (\Throwable $e) {
        fwrite(STDERR, "Fatal error: " . $e->getMessage() . "\n");
        fwrite(STDERR, "Stack trace:\n" . $e->getTraceAsString() . "\n");
        exit(1);
    }
}

// Run the application
main($argv);