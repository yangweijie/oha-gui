<?php

/**
 * Fix for real-time output display issue
 * 
 * The problem is that libui doesn't have built-in timer support for periodic updates.
 * We need to modify the TestExecutor to handle real-time output properly.
 */

require_once 'vendor/autoload.php';

use OhaGui\Core\TestExecutor;
use OhaGui\Core\OhaCommandBuilder;
use OhaGui\Models\TestConfiguration;

echo "Testing real-time output fix...\n";

// Create test configuration
$config = new TestConfiguration();
$config->url = 'http://localhost:8080';
$config->method = 'GET';
$config->concurrentConnections = 10;
$config->duration = 5; // Shorter test for debugging
$config->timeout = 5;
$config->headers = [
    'Content-Type' => 'application/json',
    'User-Agent' => 'OHA-GUI-Tool'
];

// Build command
$builder = new OhaCommandBuilder();
$command = $builder->buildCommand($config);

echo "Command: $command\n";
echo "Starting test with real-time output...\n";
echo str_repeat("-", 50) . "\n";

// Create executor
$executor = new TestExecutor();

// Track output
$outputReceived = false;
$lastOutputTime = time();

try {
    $executor->executeTest(
        $command,
        function($output) use (&$outputReceived, &$lastOutputTime) {
            $outputReceived = true;
            $lastOutputTime = time();
            echo "[REALTIME] " . trim($output) . "\n";
            flush(); // Force output to display immediately
        },
        function($error) {
            echo "[ERROR] " . print_r($error, true) . "\n";
        },
        function($exitCode, $error) {
            echo "[COMPLETED] Exit code: $exitCode\n";
            if ($error) {
                echo "[COMPLETION ERROR] " . print_r($error, true) . "\n";
            }
        }
    );

    // Monitor the test execution
    $startTime = time();
    $maxWaitTime = 30; // Maximum 30 seconds
    
    echo "Monitoring test execution...\n";
    
    while ($executor->isRunning()) {
        // Check for timeout
        if ((time() - $startTime) > $maxWaitTime) {
            echo "Test taking too long, stopping...\n";
            $executor->stopTest();
            break;
        }
        
        // Check if we haven't received output for too long
        if ($outputReceived && (time() - $lastOutputTime) > 10) {
            echo "No output received for 10 seconds, checking status...\n";
        }
        
        // Small delay to prevent busy waiting
        usleep(100000); // 100ms
        
        // Show progress indicator
        echo ".";
        flush();
    }
    
    echo "\n" . str_repeat("-", 50) . "\n";
    echo "Test completed!\n";
    echo "Final output:\n";
    echo $executor->getOutput();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nAnalysis:\n";
echo "- Output received during execution: " . ($outputReceived ? "YES" : "NO") . "\n";
echo "- Final exit code: " . $executor->getExitCode() . "\n";
echo "- Total output length: " . strlen($executor->getOutput()) . " characters\n";

// Recommendations
echo "\n" . str_repeat("=", 60) . "\n";
echo "RECOMMENDATIONS:\n";
echo "1. The issue is that oha outputs results only at the END of the test\n";
echo "2. During the test, oha shows a progress bar (TUI) which is disabled by --no-tui\n";
echo "3. With --no-tui, oha is silent during execution and only outputs final results\n";
echo "4. This is NORMAL behavior - not a bug in our GUI\n";
echo "\nSOLUTIONS:\n";
echo "1. Add a progress indicator in GUI during test execution\n";
echo "2. Show 'Test in progress...' message with animated indicator\n";
echo "3. Consider using oha with TUI enabled and parsing progress output\n";
echo "4. Add estimated completion time based on duration setting\n";