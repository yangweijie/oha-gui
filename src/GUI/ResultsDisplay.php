<?php

declare(strict_types=1);

namespace OhaGui\GUI;

use FFI\CData;
use Kingbes\Libui\Box;
use Kingbes\Libui\Control;
use Kingbes\Libui\Label;
use Kingbes\Libui\Group;
use Kingbes\Libui\Button;
use Kingbes\Libui\MultilineEntry;
use OhaGui\Models\TestResult;
use Throwable;

/**
 * Results display component for OHA GUI Tool
 * Shows test results and real-time output
 */
class ResultsDisplay extends BaseGUIComponent
{
    private $resultsGroup;
    private $vbox;
    private $statusLabel;
    private $metricsHBox;
    private $requestsPerSecLabel;
    private $totalRequestsLabel;
    private $successRateLabel;
    private $performanceLabel;
    private $outputGroup;
    private $outputEntry;
    private $saveButton;

    private string $currentOutput = "";
    private ?TestResult $lastResult = null;

    /**
     * Initialize the results display
     */
    public function __construct()
    {
        // Constructor
    }

    /**
     * Create the results display UI
     * 
     * @return CData libui control
     */
    public function createResultsDisplay(): CData
    {
        // Create main vertical box
        $mainVBox = Box::newVerticalBox();
        Box::setPadded($mainVBox, true);

        // Create results section (fixed height)
        $this->createResultsSection($mainVBox);

        // Create output section (stretch to fill available space)
        $this->createOutputSection($mainVBox);

        return $mainVBox;
    }

    /**
     * Create results metrics section
     * 
     * @param mixed $parent
     */
    private function createResultsSection(mixed $parent): void
    {
        // Create results group
        $this->resultsGroup = Group::create("结果 (Results)");
        Group::setMargined($this->resultsGroup, true);

        // Create results layout
        $this->vbox = Box::newVerticalBox();
        Box::setPadded($this->vbox, true);

        // Status label
        $this->statusLabel = Label::create("Ready to run test");
        Box::append($this->vbox, $this->statusLabel, false);

        // Create metrics display
        $this->createMetricsDisplay();

        // Set results content
        Group::setChild($this->resultsGroup, $this->vbox);
        Box::append($parent, $this->resultsGroup, false);
    }

    /**
     * Create metrics display
     */
    private function createMetricsDisplay(): void
    {
        // Create horizontal box for metrics
        $this->metricsHBox = Box::newHorizontalBox();
        Box::setPadded($this->metricsHBox, true);

        // Requests per second
        $reqSecVBox = Box::newVerticalBox();
        $reqSecLabel = Label::create("Requests/sec:");
        $this->requestsPerSecLabel = Label::create("--");
        Box::append($reqSecVBox, $reqSecLabel, false);
        Box::append($reqSecVBox, $this->requestsPerSecLabel, false);
        Box::append($this->metricsHBox, $reqSecVBox, true);

        // Total requests
        $totalVBox = Box::newVerticalBox();
        $totalLabel = Label::create("Total requests:");
        $this->totalRequestsLabel = Label::create("--");
        Box::append($totalVBox, $totalLabel, false);
        Box::append($totalVBox, $this->totalRequestsLabel, false);
        Box::append($this->metricsHBox, $totalVBox, true);

        // Success rate
        $successVBox = Box::newVerticalBox();
        $successLabel = Label::create("Success rate:");
        $this->successRateLabel = Label::create("--");
        Box::append($successVBox, $successLabel, false);
        Box::append($successVBox, $this->successRateLabel, false);
        Box::append($this->metricsHBox, $successVBox, true);

        // Performance
        $perfVBox = Box::newVerticalBox();
        $perfLabel = Label::create("Performance:");
        $this->performanceLabel = Label::create("--");
        Box::append($perfVBox, $perfLabel, false);
        Box::append($perfVBox, $this->performanceLabel, false);
        Box::append($this->metricsHBox, $perfVBox, true);

        // Add metrics to results
        Box::append($this->vbox, $this->metricsHBox, false);
    }

    /**
     * Create output section
     * 
     * @param mixed $parent
     */
    private function createOutputSection(mixed $parent): void
    {
        // Create output group
        $this->outputGroup = Group::create("测试输出 (Test Output)");
        Group::setMargined($this->outputGroup, false);

        // Create output layout
        $outputVBox = Box::newVerticalBox();
        Box::setPadded($outputVBox, true);

        // Create output text area - give it maximum space to fill available area
        $this->outputEntry = MultilineEntry::create();
        Control::disable($this->outputEntry);
        MultilineEntry::setText($this->outputEntry, "Test output will appear here...");
        // This should stretch to fill available space
        Box::append($outputVBox, $this->outputEntry, true);

        // Create save button
        $buttonHBox = Box::newHorizontalBox();
        Box::setPadded($buttonHBox, true);

        $spacer = Label::create("");
        Box::append($buttonHBox, $spacer, true);

        $this->saveButton = Button::create("Save Results");
        Control::disable($this->saveButton);
        $saveCallback = function() {
            $this->onSaveResults();
        };
        Button::onClicked($this->saveButton, $saveCallback);
        Box::append($buttonHBox, $this->saveButton, false);

        Box::append($outputVBox, $buttonHBox, false);

        // Set output content
        Group::setChild($this->outputGroup, $outputVBox);
        Box::append($parent, $this->outputGroup, true);
    }

    /**
     * Update status message
     * 
     * @param string $status
     */
    public function updateStatus(string $status): void
    {
        if ($this->statusLabel !== null) {
            Label::setText($this->statusLabel, $status);
        }
    }

    /**
     * Display test results
     * 
     * @param TestResult $result
     */
    public function displayResults(TestResult $result): void
    {
        $this->lastResult = $result;

        // Update status
        $this->updateStatus("Test completed");

        // Update metrics
        if ($this->requestsPerSecLabel !== null) {
            Label::setText($this->requestsPerSecLabel, number_format($result->requestsPerSecond, 2));
        }

        if ($this->totalRequestsLabel !== null) {
            Label::setText($this->totalRequestsLabel, number_format($result->totalRequests));
        }

        if ($this->successRateLabel !== null) {
            Label::setText($this->successRateLabel, number_format($result->successRate, 2) . '%');
        }

        if ($this->performanceLabel !== null) {
            $performance = $this->calculatePerformanceRating($result);
            Label::setText($this->performanceLabel, $performance);
        }

        // Enable save button
        if ($this->saveButton !== null) {
            Control::enable($this->saveButton);
        }
    }

    /**
     * Append output text (for real-time streaming)
     * 
     * @param string $output
     */
    public function appendOutput(string $output): void
    {
        $this->currentOutput .= $output;
        
        if ($this->outputEntry !== null) {
            MultilineEntry::setText($this->outputEntry, $this->currentOutput);
            
            // Scroll to bottom (if supported by libui)
            // Note: libui may not support automatic scrolling to bottom
        }
    }

    /**
     * Set output text (replace all content)
     * 
     * @param string $output
     */
    public function setOutput(string $output): void
    {
        $this->currentOutput = $output;
        
        if ($this->outputEntry !== null) {
            MultilineEntry::setText($this->outputEntry, $output);
            
            // Force a refresh of the control
            // This might help with display issues in some cases
            // Control::show($this->outputEntry); // Uncomment if needed
        }
    }

    /**
     * Clear output and reset metrics
     */
    public function clearOutput(): void
    {
        $this->currentOutput = "";
        $this->lastResult = null;

        // Clear output
        if ($this->outputEntry !== null) {
            MultilineEntry::setText($this->outputEntry, "Test output will appear here...");
        }

        // Reset metrics
        if ($this->requestsPerSecLabel !== null) {
            Label::setText($this->requestsPerSecLabel, "--");
        }

        if ($this->totalRequestsLabel !== null) {
            Label::setText($this->totalRequestsLabel, "--");
        }

        if ($this->successRateLabel !== null) {
            Label::setText($this->successRateLabel, "--");
        }

        if ($this->performanceLabel !== null) {
            Label::setText($this->performanceLabel, "--");
        }

        // Disable save button
        if ($this->saveButton !== null) {
            Control::disable($this->saveButton);
        }

        // Update status
        $this->updateStatus("Ready to run test");
    }

    /**
     * Show test running status
     */
    public function showTestRunning(): void
    {
        $this->updateStatus("Test running...");
        $this->clearOutput();
        $this->setOutput("Starting test..." . PHP_EOL . PHP_EOL . "Test is running. Please wait for completion." . PHP_EOL . "Note: oha tool outputs results only after completion." . PHP_EOL);
    }

    /**
     * Show test stopped status
     */
    public function showTestStopped(): void
    {
        $this->updateStatus("Test stopped");
        $this->appendOutput(PHP_EOL . "--- Test stopped by user ---" . PHP_EOL);
    }

    /**
     * Show error message
     * 
     * @param string $error
     */
    public function showError(string $error): void
    {
        $this->updateStatus("Error: " . $error);
        $this->setOutput("Error: " . $error . PHP_EOL);
    }

    /**
     * Calculate performance rating based on results
     * 
     * @param TestResult $result
     * @return string
     */
    private function calculatePerformanceRating(TestResult $result): string
    {
        $rps = $result->requestsPerSecond;
        $successRate = $result->successRate;

        // Simple performance rating based on requests per second and success rate
        if ($successRate < 95) {
            return "Poor (Low Success Rate)";
        } elseif ($rps < 10) {
            return "Poor";
        } elseif ($rps < 50) {
            return "Fair";
        } elseif ($rps < 100) {
            return "Good";
        } elseif ($rps < 500) {
            return "Very Good";
        } else {
            return "Excellent";
        }
    }

    /**
     * Handle save results button click
     */
    private function onSaveResults(): void
    {
        if ($this->lastResult === null) {
            return;
        }

        try {
            // Generate filename with timestamp
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "oha_results_{$timestamp}.txt";

            // Create results content
            $content = $this->formatResultsForSave($this->lastResult);

            // Save to file (in a real implementation, you might want to show a file dialog)
            $saved = file_put_contents($filename, $content);

            if ($saved !== false) {
                $this->updateStatus("Results saved to: " . $filename);
            } else {
                $this->updateStatus("Failed to save results");
            }

        } catch (Throwable $e) {
            $this->updateStatus("Error saving results: " . $e->getMessage());
        }
    }

    /**
     * Format results for saving to file
     * 
     * @param TestResult $result
     * @return string
     */
    private function formatResultsForSave(TestResult $result): string
    {
        $content = [];
        $content[] = "OHA GUI Tool - Test Results";
        $content[] = "Generated: " . date('Y-m-d H:i:s');
        $content[] = str_repeat("=", 50);
        $content[] = "";
        $content[] = "METRICS:";
        $content[] = "Requests per second: " . number_format($result->requestsPerSecond, 2);
        $content[] = "Total requests: " . number_format($result->totalRequests);
        $content[] = "Failed requests: " . number_format($result->failedRequests);
        $content[] = "Success rate: " . number_format($result->successRate, 2) . '%';
        $content[] = "Performance: " . $this->calculatePerformanceRating($result);
        $content[] = "";
        $content[] = "RAW OUTPUT:";
        $content[] = str_repeat("-", 50);
        $content[] = $result->rawOutput;

        return implode("\n", $content);
    }

    /**
     * Get current output text
     * 
     * @return string
     */
    public function getCurrentOutput(): string
    {
        return $this->currentOutput;
    }

    /**
     * Get last test result
     * 
     * @return TestResult|null
     */
    public function getLastResult(): ?TestResult
    {
        return $this->lastResult;
    }

    /**
     * Check if results are available
     * 
     * @return bool
     */
    public function hasResults(): bool
    {
        return $this->lastResult !== null;
    }

    /**
     * Cleanup resources
     */
    public function cleanup(): void
    {
        try {
            // Clear references to libui controls
            $this->resultsGroup = null;
            $this->vbox = null;
            $this->statusLabel = null;
            $this->metricsHBox = null;
            $this->requestsPerSecLabel = null;
            $this->totalRequestsLabel = null;
            $this->successRateLabel = null;
            $this->performanceLabel = null;
            $this->outputGroup = null;
            $this->outputEntry = null;
            $this->saveButton = null;
            
            // Clear data
            $this->currentOutput = "";
            $this->lastResult = null;
            
        } catch (Throwable $e) {
            error_log("ResultsDisplay cleanup error: " . $e->getMessage());
        }
    }
}
