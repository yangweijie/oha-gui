<?php

/**
 * Test queueMain integration for GUI updates
 */

require_once 'vendor/autoload.php';

use Kingbes\Libui\App;
use Kingbes\Libui\Window;
use Kingbes\Libui\Control;
use Kingbes\Libui\Box;
use Kingbes\Libui\Button;
use Kingbes\Libui\ProgressBar;
use Kingbes\Libui\Label;
use Kingbes\Libui\MultilineEntry;
use OhaGui\Core\TestExecutor;
use OhaGui\Core\OhaCommandBuilder;
use OhaGui\Models\TestConfiguration;

echo "=== Testing queueMain GUI Updates ===\n";

// Initialize libui
App::init();

// Create window
$window = Window::create("Progress Test", 600, 400, 0);
Window::setMargined($window, true);

// Create layout
$box = Box::newVerticalBox();
Box::setPadded($box, true);
Window::setChild($window, $box);

// Create progress bar
$progressBar = ProgressBar::create();
Box::append($box, $progressBar, false);

// Create status label
$statusLabel = Label::create("Ready to test...");
Box::append($box, $statusLabel, false);

// Create output area
$outputArea = MultilineEntry::create();
MultilineEntry::setReadOnly($outputArea, true);
Box::append($box, $outputArea, true);

// Create test button
$testButton = Button::create("Start Test");
Box::append($box, $testButton, false);

// Test executor
$executor = new TestExecutor();

// Button click handler
Button::onClicked($testButton, function($btn) use ($progressBar, $statusLabel, $outputArea, $executor) {
    echo "Starting test...\n";
    
    // Disable button
    Control::disable($btn);
    
    // Set initial state
    Label::setText($statusLabel, "🔄 Test starting...");
    ProgressBar::setValue($progressBar, -1); // Indeterminate
    MultilineEntry::setText($outputArea, "Preparing test...\n");
    
    // Create test config
    $config = new TestConfiguration();
    $config->url = 'http://httpbin.org/get';
    $config->method = 'GET';
    $config->concurrentConnections = 3;
    $config->duration = 5;
    $config->timeout = 5;
    
    $builder = new OhaCommandBuilder();
    $command = $builder->buildCommand($config);
    
    try {
        $executor->executeTest(
            $command,
            function($output) use ($outputArea, $statusLabel) {
                // Use queueMain for thread-safe GUI updates
                App::queueMain(function($data) use ($output, $outputArea, $statusLabel) {
                    $current = MultilineEntry::text($outputArea);
                    MultilineEntry::setText($outputArea, $current . $output);
                    Label::setText($statusLabel, "🔄 Test running... (receiving output)");
                });
            },
            function($error) use ($outputArea, $statusLabel) {
                App::queueMain(function($data) use ($error, $outputArea, $statusLabel) {
                    $current = MultilineEntry::text($outputArea);
                    MultilineEntry::setText($outputArea, $current . "ERROR: " . print_r($error, true) . "\n");
                    Label::setText($statusLabel, "❌ Test error occurred");
                });
            },
            function($exitCode, $error = null) use ($progressBar, $statusLabel, $outputArea, $btn) {
                App::queueMain(function($data) use ($exitCode, $error, $progressBar, $statusLabel, $outputArea, $btn) {
                    // Set progress to 100%
                    ProgressBar::setValue($progressBar, 100);
                    
                    if ($exitCode === 0) {
                        Label::setText($statusLabel, "✅ Test completed successfully!");
                    } else {
                        Label::setText($statusLabel, "❌ Test failed (exit code: $exitCode)");
                    }
                    
                    $current = MultilineEntry::text($outputArea);
                    MultilineEntry::setText($outputArea, $current . "\n=== TEST COMPLETED ===\n");
                    
                    // Re-enable button
                    Control::enable($btn);
                });
            }
        );
        
        // Start progress monitoring
        $startMonitoring = function() use ($executor, $progressBar, $statusLabel, &$startMonitoring) {
            App::queueMain(function($data) use ($executor, $progressBar, $statusLabel, $startMonitoring) {
                if ($executor->isRunning()) {
                    // Keep showing indeterminate progress
                    ProgressBar::setValue($progressBar, -1);
                    Label::setText($statusLabel, "🔄 Test in progress...");
                    
                    // Schedule next check
                    $startMonitoring();
                }
            });
        };
        
        $startMonitoring();
        
    } catch (Exception $e) {
        Label::setText($statusLabel, "❌ Failed to start test");
        MultilineEntry::setText($outputArea, "Error: " . $e->getMessage());
        Control::enable($btn);
    }
});

// Window close handler
Window::onClosing($window, function($window) use ($executor) {
    if ($executor->isRunning()) {
        $executor->stopTest();
    }
    App::quit();
    return 1;
});

// Show window
Control::show($window);

echo "GUI window created. Test the progress bar behavior!\n";
echo "Click 'Start Test' to see queueMain in action.\n";

// Start main loop
App::main();