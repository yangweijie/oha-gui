<?php

/**
 * OHA GUI Tool - Main Entry Point
 * 
 * This is the main entry point for the OHA GUI application.
 * It handles autoloading, dependency initialization, and application startup.
 * 
 * Requirements: 5.1, 5.2, 5.3
 * - Cross-platform compatibility (Windows, macOS, Linux)
 * - Proper application lifecycle management
 * - Error handling and graceful shutdown
 */

declare(strict_types=1);

// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Shanghai');

// Define application constants
define('APP_NAME', 'OHA GUI Tool');
define('APP_VERSION', '1.0.0');
define('APP_ROOT', __DIR__);

// Import required classes (must be at top level)
use OhaGui\App\OhaGuiApp;
use OhaGui\Utils\CrossPlatform;
use OhaGui\Utils\UserGuidance;

try {
    // Check if composer autoloader exists
    $autoloaderPath = APP_ROOT . '/vendor/autoload.php';
    if (!file_exists($autoloaderPath)) {
        throw new RuntimeException(
            "Composer autoloader not found. Please run 'composer install' first.\n" .
            "Expected location: " . $autoloaderPath
        );
    }

    // Load composer autoloader
    require_once $autoloaderPath;

    // Check if kingbes/libui package is available
    if (!class_exists('Kingbes\Libui\App')) {
        throw new RuntimeException(
            "The kingbes/libui package is not available.\n" .
            "Please run 'composer install' to install the required dependencies."
        );
    }



    // Display comprehensive startup information
    echo str_repeat("=", 60) . "\n";
    echo "🚀 " . APP_NAME . " v" . APP_VERSION . "\n";
    echo str_repeat("=", 60) . "\n";
    echo "Platform: " . CrossPlatform::getOperatingSystem() . " (" . PHP_OS . ")\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "Architecture: " . php_uname('m') . "\n";
    echo "Working Directory: " . getcwd() . "\n";
    echo "Memory Limit: " . ini_get('memory_limit') . "\n";
    echo str_repeat("-", 60) . "\n";

    // Check for oha binary availability with detailed feedback
    echo "🔍 Checking oha binary availability...\n";
    $ohaBinaryPath = CrossPlatform::findOhaBinaryPath();
    if ($ohaBinaryPath === null) {
        echo "⚠️  WARNING: oha binary not found in bin directory\n";
        echo "   Location checked: " . dirname(__DIR__) . "/bin/\n";
        echo "   Expected filename: " . (CrossPlatform::isWindows() ? 'oha.exe' : 'oha') . "\n";
        echo "   Impact: Testing functionality will be limited\n\n";
        echo "📋 " . UserGuidance::getInstallationInstructions() . "\n";
    } else {
        echo "✅ OHA Binary found: " . $ohaBinaryPath . "\n";
        
        // Test oha binary functionality
        if (CrossPlatform::isOhaAvailable()) {
            echo "✅ OHA Binary is functional\n";
            
            // Try to get version information
            $result = CrossPlatform::executeCommand(escapeshellarg($ohaBinaryPath) . ' --version');
            if ($result['success'] && !empty($result['output'])) {
                echo "📦 Version: " . trim(implode(' ', $result['output'])) . "\n";
            }
        } else {
            echo "⚠️  WARNING: OHA Binary found but not functional\n";
            echo "   This may indicate permission issues or a corrupted binary\n";
        }
    }

    echo str_repeat("-", 60) . "\n";
    echo "🎨 Initializing GUI components...\n";

    // Create and run the application
    echo "🏗️  Creating application instance...\n";
    $app = new OhaGuiApp();
    
    // Set up signal handlers for graceful shutdown (Unix-like systems)
    if (function_exists('pcntl_signal')) {
        echo "🛡️  Setting up signal handlers for graceful shutdown...\n";
        pcntl_signal(SIGINT, function() use ($app) {
            echo "\n🛑 Received interrupt signal (Ctrl+C). Shutting down gracefully...\n";
            $app->shutdown();
            exit(0);
        });
        
        pcntl_signal(SIGTERM, function() use ($app) {
            echo "\n🛑 Received termination signal. Shutting down gracefully...\n";
            $app->shutdown();
            exit(0);
        });
    }

    echo "✅ Application initialized successfully\n";
    echo str_repeat("=", 60) . "\n";
    echo "🎯 Starting main application loop...\n";
    echo "   Use Ctrl+C to exit gracefully\n";
    echo "   Close the window or use File > Quit to exit\n";
    echo str_repeat("=", 60) . "\n\n";

    // Run the application
    $app->run();
    
    echo "\n✅ Application exited normally\n";

} catch (Throwable $e) {
    // Handle any uncaught exceptions with comprehensive user-friendly guidance
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "❌ FATAL ERROR OCCURRED\n";
    echo str_repeat("=", 60) . "\n";
    
    $errorMessage = "Error: " . $e->getMessage() . "\n";
    $errorMessage .= "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    $errorMessage .= "Type: " . get_class($e) . "\n\n";
    
    // Add stack trace for debugging
    $errorMessage .= "Stack Trace:\n";
    $errorMessage .= $e->getTraceAsString() . "\n\n";
    
    // Add comprehensive troubleshooting guidance
    $errorMessage .= str_repeat("-", 40) . "\n";
    $errorMessage .= "TROUBLESHOOTING GUIDE\n";
    $errorMessage .= str_repeat("-", 40) . "\n";
    $errorMessage .= UserGuidance::getTroubleshootingGuide();
    
    // Add specific error guidance based on error type
    if (strpos($e->getMessage(), 'libui') !== false) {
        $errorMessage .= "\n🔧 LIBUI SPECIFIC ISSUES:\n";
        $errorMessage .= "• Ensure kingbes/libui is properly installed: composer install\n";
        $errorMessage .= "• Check that your system supports GUI applications\n";
        $errorMessage .= "• Try running in a desktop environment (not headless)\n";
        $errorMessage .= "• Verify display settings and graphics drivers\n";
    }
    
    if (strpos($e->getMessage(), 'autoload') !== false) {
        $errorMessage .= "\n🔧 AUTOLOADER ISSUES:\n";
        $errorMessage .= "• Run: composer install\n";
        $errorMessage .= "• Check that vendor/autoload.php exists\n";
        $errorMessage .= "• Verify composer.json is valid\n";
    }
    
    // Log error to file for debugging
    $logFile = 'error_' . date('Y-m-d_H-i-s') . '.log';
    file_put_contents($logFile, $errorMessage);
    $errorMessage .= "\n📝 Error details saved to: " . $logFile . "\n";
    
    // Try to display error in GUI if possible, otherwise use console
    if (class_exists('Kingbes\Libui\App')) {
        try {
            \Kingbes\Libui\App::init();
            if (class_exists('Kingbes\Libui\Window\MsgBox')) {
                \Kingbes\Libui\Window\MsgBox::error(null, 'OHA GUI Tool - Fatal Error', $errorMessage);
            }
            \Kingbes\Libui\App::unInit();
        } catch (Throwable $guiError) {
            // If GUI error display fails, fall back to console
            echo $errorMessage;
            echo "\n⚠️  Additional GUI error: " . $guiError->getMessage() . "\n";
        }
    } else {
        echo $errorMessage;
    }
    
    // If not running in CLI, also try to output to browser
    if (php_sapi_name() !== 'cli') {
        echo "<pre>" . htmlspecialchars($errorMessage) . "</pre>";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Application terminated due to fatal error\n";
    echo "Exit code: 1\n";
    echo str_repeat("=", 60) . "\n";
    
    // Exit with error code
    exit(1);
}