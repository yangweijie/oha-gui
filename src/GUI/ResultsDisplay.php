<?php

namespace OhaGui\GUI;

use Kingbes\Libui\Group;
use Kingbes\Libui\Box;
use Kingbes\Libui\Label;
use Kingbes\Libui\MultilineEntry;
use Kingbes\Libui\Button;
use Kingbes\Libui\ProgressBar;
use Kingbes\Libui\Control;
use Kingbes\Libui\Window;
use FFI\CData;
use OhaGui\Models\TestResult;
use Exception;

/**
 * Results Display component for showing test output and metrics
 * 
 * Displays formatted test results, real-time output, and provides save functionality
 */
class ResultsDisplay
{
    private CData $group;
    private CData $metricsBox;
    private CData $outputArea;
    private CData $saveButton;
    private CData $clearButton;
    private CData $progressBar;
    
    // Metric labels
    private CData $requestsPerSecLabel;
    private CData $totalRequestsLabel;
    private CData $successRateLabel;
    private CData $performanceLabel;
    private CData $statusLabel;
    
    private ?TestResult $currentResult = null;
    private bool $isTestRunning = false;
    
    private $onSaveResultsCallback = null;

    /**
     * Initialize the results display
     */
    public function __construct()
    {
        $this->createUI();
        $this->resetDisplay();
    }

    /**
     * Create the results display UI
     * 
     * @return void
     */
    private function createUI(): void
    {
        // Create main group
        $this->group = Group::create('Test Results');
        Group::setMargined($this->group, true);

        // Create main vertical box
        $mainBox = Box::newVerticalBox();
        Box::setPadded($mainBox, true);

        // Create metrics section
        $this->createMetricsSection($mainBox);

        // Create progress bar
        $this->progressBar = ProgressBar::create();
        ProgressBar::setValue($this->progressBar, 0);
        Control::hide($this->progressBar); // Initially hidden
        Box::append($mainBox, $this->progressBar, false);

        // Create output area
        $this->createOutputSection($mainBox);

        // Create button section
        $this->createButtonSection($mainBox);

        // Set main box as group child
        Group::setChild($this->group, $mainBox);
    }

    /**
     * Create the metrics display section
     * 
     * @param CData $parentBox
     * @return void
     */
    private function createMetricsSection(CData $parentBox): void
    {
        $metricsGroup = Group::create('Metrics');
        Group::setMargined($metricsGroup, true);

        $this->metricsBox = Box::newVerticalBox();
        Box::setPadded($this->metricsBox, true);

        // Status label
        $this->statusLabel = Label::create('Ready to run test');
        Box::append($this->metricsBox, $this->statusLabel, false);

        // Requests per second
        $this->requestsPerSecLabel = Label::create('Requests/sec: --');
        Box::append($this->metricsBox, $this->requestsPerSecLabel, false);

        // Total requests
        $this->totalRequestsLabel = Label::create('Total requests: --');
        Box::append($this->metricsBox, $this->totalRequestsLabel, false);

        // Success rate
        $this->successRateLabel = Label::create('Success rate: --');
        Box::append($this->metricsBox, $this->successRateLabel, false);

        // Performance rating
        $this->performanceLabel = Label::create('Performance: --');
        Box::append($this->metricsBox, $this->performanceLabel, false);

        Group::setChild($metricsGroup, $this->metricsBox);
        Box::append($parentBox, $metricsGroup, false);
    }

    /**
     * Create the output display section
     * 
     * @param CData $parentBox
     * @return void
     */
    private function createOutputSection(CData $parentBox): void
    {
        $outputGroup = Group::create('Test Output');
        Group::setMargined($outputGroup, true);

        $this->outputArea = MultilineEntry::createNonWrapping();
        MultilineEntry::setReadOnly($this->outputArea, true);
        MultilineEntry::setText($this->outputArea, 'Test output will appear here...');

        Group::setChild($outputGroup, $this->outputArea);
        Box::append($parentBox, $outputGroup, true);
    }

    /**
     * Create the button section
     * 
     * @param CData $parentBox
     * @return void
     */
    private function createButtonSection(CData $parentBox): void
    {
        $buttonBox = Box::newHorizontalBox();
        Box::setPadded($buttonBox, true);

        // Save results button
        $this->saveButton = Button::create('Save Results');
        Control::disable($this->saveButton); // Initially disabled
        Box::append($buttonBox, $this->saveButton, false);

        // Clear output button
        $this->clearButton = Button::create('Clear Output');
        Box::append($buttonBox, $this->clearButton, false);

        Box::append($parentBox, $buttonBox, false);

        $this->setupButtonHandlers();
    }

    /**
     * Setup button event handlers
     * 
     * @return void
     */
    private function setupButtonHandlers(): void
    {
        Button::onClicked($this->saveButton, function($button) {
            $this->saveResults();
        });

        Button::onClicked($this->clearButton, function($button) {
            $this->clearOutput();
        });
    }

    /**
     * Update the metrics display with test results
     * 
     * @param TestResult $result
     * @return void
     */
    public function updateMetrics(TestResult $result): void
    {
        $this->currentResult = $result;

        // Update metric labels
        Label::setText($this->requestsPerSecLabel, 
            sprintf('Requests/sec: %.2f', $result->requestsPerSecond));
        
        Label::setText($this->totalRequestsLabel, 
            sprintf('Total requests: %d', $result->totalRequests));
        
        Label::setText($this->successRateLabel, 
            sprintf('Success rate: %.2f%%', $result->successRate));
        
        Label::setText($this->performanceLabel, 
            sprintf('Performance: %s', $result->getPerformanceRating()));

        // Update status
        if ($result->isSuccessful()) {
            Label::setText($this->statusLabel, '✓ Test completed successfully');
        } else {
            Label::setText($this->statusLabel, 
                sprintf('⚠ Test completed with %d failures', $result->failedRequests));
        }

        // Enable save button
        Control::enable($this->saveButton);
    }

    /**
     * Append output text to the display area
     * 
     * @param string $output
     * @return void
     */
    public function appendOutput(string $output): void
    {
        MultilineEntry::append($this->outputArea, $output);
    }

    /**
     * Set the complete output text
     * 
     * @param string $output
     * @return void
     */
    public function setOutput(string $output): void
    {
        MultilineEntry::setText($this->outputArea, $output);
    }

    /**
     * Clear the output display
     * 
     * @return void
     */
    public function clearOutput(): void
    {
        MultilineEntry::setText($this->outputArea, '');
        $this->resetDisplay();
    }

    /**
     * Reset the display to initial state
     * 
     * @return void
     */
    public function resetDisplay(): void
    {
        $this->currentResult = null;
        $this->isTestRunning = false;

        // Reset metric labels
        Label::setText($this->statusLabel, 'Ready to run test');
        Label::setText($this->requestsPerSecLabel, 'Requests/sec: --');
        Label::setText($this->totalRequestsLabel, 'Total requests: --');
        Label::setText($this->successRateLabel, 'Success rate: --');
        Label::setText($this->performanceLabel, 'Performance: --');

        // Hide progress bar and disable save button
        Control::hide($this->progressBar);
        Control::disable($this->saveButton);
    }

    /**
     * Show test is starting
     * 
     * @return void
     */
    public function showTestStarting(): void
    {
        $this->isTestRunning = true;
        Label::setText($this->statusLabel, '⏳ Test is running...');
        Control::show($this->progressBar);
        ProgressBar::setValue($this->progressBar, -1); // Indeterminate progress
        MultilineEntry::setText($this->outputArea, 'Starting test...\n');
    }

    /**
     * Show test has stopped
     * 
     * @return void
     */
    public function showTestStopped(): void
    {
        $this->isTestRunning = false;
        Label::setText($this->statusLabel, '⏹ Test stopped');
        Control::hide($this->progressBar);
    }

    /**
     * Show test completion
     * 
     * @return void
     */
    public function showTestCompleted(): void
    {
        $this->isTestRunning = false;
        Control::hide($this->progressBar);
        
        if ($this->currentResult === null) {
            Label::setText($this->statusLabel, '✓ Test completed');
        }
    }

    /**
     * Show test error
     * 
     * @param string $error
     * @return void
     */
    public function showTestError(string $error): void
    {
        $this->isTestRunning = false;
        Label::setText($this->statusLabel, '❌ Test failed: ' . $error);
        Control::hide($this->progressBar);
        MultilineEntry::append($this->outputArea, "\nError: " . $error . "\n");
    }

    /**
     * Update progress during test execution
     * 
     * @param int $progress Progress percentage (0-100, or -1 for indeterminate)
     * @return void
     */
    public function updateProgress(int $progress): void
    {
        if ($this->progressBar !== null) {
            if ($progress === -1) {
                // Indeterminate progress (pulsing animation)
                ProgressBar::setValue($this->progressBar, -1);
            } else {
                // Specific progress percentage
                ProgressBar::setValue($this->progressBar, max(0, min(100, $progress)));
            }
        }
    }

    /**
     * Save the current test results
     * 
     * @return void
     */
    private function saveResults(): void
    {
        if ($this->currentResult === null) {
            return;
        }

        if ($this->onSaveResultsCallback) {
            ($this->onSaveResultsCallback)($this->currentResult);
        }
    }

    /**
     * Set callback for save results event
     * 
     * @param callable $callback
     * @return void
     */
    public function setOnSaveResultsCallback(callable $callback): void
    {
        $this->onSaveResultsCallback = $callback;
    }

    /**
     * Get the group control
     * 
     * @return CData
     */
    public function getControl(): CData
    {
        return $this->group;
    }

    /**
     * Get the current test result
     * 
     * @return TestResult|null
     */
    public function getCurrentResult(): ?TestResult
    {
        return $this->currentResult;
    }

    /**
     * Check if test is currently running
     * 
     * @return bool
     */
    public function isTestRunning(): bool
    {
        return $this->isTestRunning;
    }

    /**
     * Get the current output text
     * 
     * @return string
     */
    public function getOutput(): string
    {
        return MultilineEntry::text($this->outputArea);
    }

    /**
     * Display error message in the results area
     * 
     * @param string $errorMessage
     * @return void
     */
    public function displayError(string $errorMessage): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $formattedError = "\n" . str_repeat("=", 50) . "\n";
        $formattedError .= "❌ ERROR - {$timestamp}\n";
        $formattedError .= str_repeat("=", 50) . "\n";
        $formattedError .= $errorMessage . "\n";
        $formattedError .= str_repeat("=", 50) . "\n\n";
        
        $this->appendOutput($formattedError);
        $this->showTestError($errorMessage);
    }

    /**
     * Clean up resources and libui controls
     * 
     * @return void
     */
    public function cleanup(): void
    {
        try {
            // Clear callbacks to prevent memory leaks
            $this->onSaveResultsCallback = null;
            
            // Clear result data
            $this->currentResult = null;
            $this->isTestRunning = false;
            
            // Note: libui controls are automatically cleaned up when parent is destroyed
            // We don't need to explicitly destroy individual controls
            
        } catch (Exception $e) {
            error_log("Error during ResultsDisplay cleanup: " . $e->getMessage());
        }
    }
}