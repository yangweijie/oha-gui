<?php

declare(strict_types=1);

namespace OhaGui\GUI;

use Exception;
use FFI\CData;
use Kingbes\Libui\App;
use Kingbes\Libui\Control;
use Kingbes\Libui\Window;
use Kingbes\Libui\Box;
use Kingbes\Libui\Label;
use Kingbes\Libui\Button;
use Kingbes\Libui\Group;
use Kingbes\Libui\MultilineEntry;
use Kingbes\Libui\ProgressBar;
use Kingbes\Libui\Separator;
use OhaGui\Core\TestExecutor;
use OhaGui\Core\OhaCommandBuilder;
use OhaGui\Core\ResultParser;
use OhaGui\Core\ConfigurationManager;
use OhaGui\Models\TestConfiguration;

/**
 * Main window class for OHA GUI Tool
 * Creates and manages the main application window with all UI components
 */
class MainWindow extends BaseGUIComponent
{
    private $window;
    private $vbox;
    private ?ConfigurationForm $configForm = null;
    private ?ConfigurationDropdown $configDropdown = null;
    private ?ResultsDisplay $resultsDisplay = null;
    private ?ConfigurationManagerWindow $configManagerWindow = null;
    private ?RequestOverview $requestOverview = null;
    private $startButton = null;
    private $stopButton = null;
    private $progressBar = null;
    private ?TestConfiguration $currentConfig = null;
    
    // UI elements for the new layout
    private $statusLabel = null;
    private $requestsPerSecLabel = null;
    private $totalRequestsLabel = null;
    private $successRateLabel = null;
    private $performanceLabel = null;
    private $outputEntry = null;
    private $saveButton = null;
    
    // Store current output text
    private string $currentOutput = "";
    
    // Configuration group for refresh
    private $configGroup = null;
    
    // Core components for test execution
    private ?TestExecutor $testExecutor = null;
    private ?OhaCommandBuilder $commandBuilder = null;
    private ?ResultParser $resultParser = null;
    private ?ConfigurationManager $configManager = null;

    /**
     * Initialize the main window
     */
    public function __construct()
    {
        // Initialize core components
        $this->testExecutor = new TestExecutor();
        $this->commandBuilder = new OhaCommandBuilder();
        $this->resultParser = new ResultParser();
        $this->configManager = new ConfigurationManager();
        
        $this->createWindow();
        $this->createLayout();
        $this->setupEventHandlers();
    }

    /**
     * Create the main window with improved properties
     */
    private function createWindow(): void
    {
        // Create window with title and improved dimensions
        $this->window = Window::create(
            "OHA GUI Tool v1.0.0 - HTTP 压力测试工具",
            900,  // width - increased for better layout
            600,  // height - reduced to decrease output area
            1     // has menubar
        );

        // Set window properties for better user experience
        Window::setMargined($this->window, true);
        
        // Set minimum window size to prevent UI from becoming unusable
        // Note: libui may not support this directly, but we document the intention
        // Minimum recommended size: 600x500
        
        // Center window on screen (if supported by libui)
        // This is a best-effort approach as libui may not support window positioning
        
        // Set window to be resizable (default behavior)
        // Users can resize the window to their preference
    }

    /**
     * Create the main layout with all components
     */
    private function createLayout(): void
    {
        // Create main vertical box
        $this->vbox = Box::newVerticalBox();
        Box::setPadded($this->vbox, false); // Remove padding

        // Create configuration section (fixed height)
        $this->createConfigurationSection();

        // Create a horizontal box for input and results sections
        $inputResultsHBox = Box::newHorizontalBox();
        Box::setPadded($inputResultsHBox, false); // Remove padding

        // Create request overview - give it more width
        $this->requestOverview = new RequestOverview();
        $overviewControl = $this->requestOverview->createOverview();
        Box::append($inputResultsHBox, $overviewControl, true); // Allow to stretch

        // Create results display (metrics only, not the full component)
        $this->resultsDisplay = new ResultsDisplay();
        $resultsMetricsControl = $this->createResultsMetricsSection();
        Box::append($inputResultsHBox, $resultsMetricsControl, false); // Allow to stretch

        // Add the input/results horizontal box to main layout
        Box::append($this->vbox, $inputResultsHBox, false); // Fixed height

        // Create test control buttons (fixed height)
        $this->createTestControlButtons();

        // Create output display section - give it less space
        $this->currentOutput = "Test output will appear here...";
        $outputControl = $this->createOutputSection();
        Box::append($this->vbox, $outputControl, true); // Stretch to fill space

        // Set the main content
        Window::setChild($this->window, $this->vbox);
    }

    /**
     * Create the configuration section with dropdown and management button
     */
    private function createConfigurationSection(): void
    {
        // Create configuration group
        $configGroup = Group::create("");
        Group::setMargined($configGroup, false);

        // Create horizontal box for configuration controls
        $configHBox = Box::newHorizontalBox();
        Box::setPadded($configHBox, false); // Remove padding

        // Create configuration label
        $configLabel = Label::create("配置:");
        Box::append($configHBox, $configLabel, false);

        // Create configuration dropdown
        $this->configDropdown = new ConfigurationDropdown();
        $dropdownControl = $this->configDropdown->createDropdown();
        Box::append($configHBox, $dropdownControl, true);

        // Create management button
        $managementButton = Button::create("管理");
        Box::append($configHBox, $managementButton, false);

        // Set up management button callback
        $callback = function() {
            $this->onManagementButtonClick();
        };
        Button::onClicked($managementButton, $callback);

        // Set group content
        Group::setChild($configGroup, $configHBox);

        // Add configuration section to main layout
        Box::append($this->vbox, $configGroup, false);

        // Add separator
        $separator = Separator::createHorizontal();
        Box::append($this->vbox, $separator, false);
        
        // Store reference to config group for later refresh
        $this->configGroup = $configGroup;
    }

    /**
     * Create test control buttons (Start/Stop) and progress bar
     */
    private function createTestControlButtons(): void
    {
        // Create horizontal box for buttons
        $buttonHBox = Box::newHorizontalBox();
        Box::setPadded($buttonHBox, true); // Remove padding

        // Create start button
        $this->startButton = Button::create("开始测试");
        Box::append($buttonHBox, $this->startButton, true);

        // Create stop button
        $this->stopButton = Button::create("停止测试");
        Box::append($buttonHBox, $this->stopButton, true);

        // Add button box to main layout
        Box::append($this->vbox, $buttonHBox, false);
        
        // Create progress bar
        $this->progressBar = ProgressBar::create();
        ProgressBar::setValue($this->progressBar, 0); // Hide progress bar initially
        Box::append($this->vbox, $this->progressBar, false);
        Control::hide($this->progressBar); // Hide progress bar initially
    }

    /**
     * Create results metrics section only (without output)
     * 
     * @return CData libui control
     */
    private function createResultsMetricsSection(): CData
    {
        // Create results group
        $resultsGroup = Group::create("结果 (Results)");
        Group::setMargined($resultsGroup, true); // Remove margins

        // Create results layout
        $vbox = Box::newVerticalBox();
        Box::setPadded($vbox, false); // Remove padding

        // Status label
        $this->statusLabel = Label::create("Ready to run test");
        Box::append($vbox, $this->statusLabel, true);

        $space = Separator::createHorizontal();
        Box::append($vbox, $space, true);

        // Create vertical metrics display
        // Requests per second
        $reqSecHBox = Box::newHorizontalBox();
        Box::setPadded($reqSecHBox, true);
        $reqSecLabel = Label::create("Requests/sec:");
        Box::append($reqSecHBox, $reqSecLabel, true);
        $this->requestsPerSecLabel = Label::create("--");
        Box::append($reqSecHBox, $this->requestsPerSecLabel, true);
        Box::append($vbox, $reqSecHBox, true);

        // Total requests
        $totalHBox = Box::newHorizontalBox();
        Box::setPadded($totalHBox, true);
        $totalLabel = Label::create("Total requests:");
        Box::append($totalHBox, $totalLabel, true);
        $this->totalRequestsLabel = Label::create("--");
        Box::append($totalHBox, $this->totalRequestsLabel, true);
        Box::append($vbox, $totalHBox, true);

        // Success rate
        $successHBox = Box::newHorizontalBox();
        Box::setPadded($successHBox, true);
        $successLabel = Label::create("Success rate:");
        Box::append($successHBox, $successLabel, true);
        $this->successRateLabel = Label::create("--");
        Box::append($successHBox, $this->successRateLabel, true);
        Box::append($vbox, $successHBox, true);

        // Performance
        $perfHBox = Box::newHorizontalBox();
        Box::setPadded($perfHBox, true);
        $perfLabel = Label::create("Performance:");
        Box::append($perfHBox, $perfLabel, true);
        $this->performanceLabel = Label::create("--");
        Box::append($perfHBox, $this->performanceLabel, true);
        Box::append($vbox, $perfHBox, true);

        // Add some extra rows for better layout
        $spacer1 = Label::create("");
        Box::append($vbox, $spacer1, true);
        
        $spacer2 = Label::create("");
        Box::append($vbox, $spacer2, true);

        // Set results content
        Group::setChild($resultsGroup, $vbox);

        return $resultsGroup;
    }

    /**
     * Create output section only (without metrics)
     * 
     * @return CData libui control
     */
    private function createOutputSection()
    {
        // Create output group
        $outputGroup = Group::create("测试输出 (Test Output)");
        Group::setMargined($outputGroup, false); // Remove margins

        // Create output layout
        $outputVBox = Box::newVerticalBox();
        Box::setPadded($outputVBox, false); // Remove padding

        // Create output text area - give it less space
        $this->outputEntry = MultilineEntry::create();
        Control::disable($this->outputEntry);
        $this->currentOutput = "Test output will appear here...";
        MultilineEntry::setText($this->outputEntry, $this->currentOutput);
        // Give it less stretch
        Box::append($outputVBox, $this->outputEntry, true);

        // Create save button
        $buttonHBox = Box::newHorizontalBox();
        Box::setPadded($buttonHBox, false); // Remove padding

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
        Group::setChild($outputGroup, $outputVBox);

        return $outputGroup;
    }

    /**
     * Setup event handlers for the window
     */
    private function setupEventHandlers(): void
    {
        // Set window closing callback
        $closingCallback = function() {
            return $this->onClosing();
        };
        Window::onClosing($this->window, $closingCallback);

        // Setup configuration dropdown selection callback
        $this->configDropdown?->onSelectionChanged(function (string $configName) {
            $this->selectConfiguration($configName);
        });

        if ($this->configForm !== null) {
            $this->configForm->setOnStartTestCallback(function() {
                $this->startTest();
            });
            
            $this->configForm->setOnStopTestCallback(function() {
                $this->stopTest();
            });
            
            $this->configForm->setOnSaveConfigCallback(function($config) {
                $this->saveConfiguration($config);
            });
        }

        // Setup button click handlers
        $this->setupButtonEventHandlers();

        // Setup keyboard shortcuts
        $this->setupKeyboardShortcuts();
    }

    /**
     * Setup button event handlers for the main window
     */
    private function setupButtonEventHandlers(): void
    {
        // Setup start button click handler
        if ($this->startButton !== null) {
            Button::onClicked($this->startButton, function() {
                $this->startTest();
            });
        }

        // Setup stop button click handler
        if ($this->stopButton !== null) {
            Button::onClicked($this->stopButton, function() {
                $this->stopTest();
            });
            
            // Disable stop button initially
            Control::disable($this->stopButton);
        }
    }

    /**
     * Setup keyboard shortcuts for the application
     */
    private function setupKeyboardShortcuts(): void
    {
        // Note: libui doesn't have direct support for keyboard shortcuts
        // This is a placeholder for where shortcut handling would be implemented
        // if the library supported it
        
        // In a full implementation, we would:
        // 1. Register Ctrl+N for new configuration
        // 2. Register Ctrl+S for save configuration
        // 3. Register F5 for start test
        // 4. Register Esc for stop test
        // 5. Register Ctrl+O for open/import configuration
        // 6. Register Ctrl+E for export configuration
        
        // For now, we document the intended shortcuts in the UI
    }

    /**
     * Show the main window
     */
    public function show(): void
    {
        Control::show($this->window);
    }

    /**
     * Hide the main window
     */
    public function hide(): void
    {
        Control::hide($this->window);
    }

    /**
     * Handle window closing event
     * 
     * @return bool true to allow closing, false to prevent
     */
    public function onClosing(): bool
    {
        // Cleanup resources before closing
        $this->cleanup();
        echo "窗口关闭\n";
        App::quit();
        // Return false to allow the window to close
        return true;
    }

    /**
     * Handle management button click
     */
    public function onManagementButtonClick(): void
    {
        if ($this->configManagerWindow === null) {
            $this->configManagerWindow = new ConfigurationManagerWindow();
            
            // Set callback for when a configuration is selected from management window
            $this->configManagerWindow->setOnConfigurationSelectedCallback(function($configName) {
                $this->onConfigurationSelectedFromManager($configName);
            });
            
            // Set up a callback to refresh dropdown when configurations change in management window
            $this->configManagerWindow->setOnConfigurationChangedCallback(function() {
                $this->refreshConfigurationDropdown();
            });
        }
        
        // Refresh the management window before showing
        $this->configManagerWindow->refreshTable();
        $this->configManagerWindow->show();
    }

    /**
     * Handle configuration selection from management window
     * 
     * @param string $configName
     */
    private function onConfigurationSelectedFromManager(string $configName): void
    {
        // Refresh the dropdown to ensure it has the latest configurations
        $this->refreshConfigurationDropdown();
        
        // Select the configuration in the dropdown and load it into the form
        $this->setSelectedConfiguration($configName);
    }

    /**
     * Refresh the configuration dropdown with latest configurations
     */
    public function refreshConfigurationDropdown(): void
    {
        if ($this->configDropdown !== null) {
            $this->configDropdown->refreshConfigurations();
        }
    }

    /**
     * Handle save results button click
     */
    private function onSaveResults(): void
    {
        // For now, just show a message that this feature is not fully implemented
        if ($this->statusLabel !== null) {
            Label::setText($this->statusLabel, "Save results feature not fully implemented");
        }
        
        // In a full implementation, this would save the current output to a file
    }

    /**
     * Set the selected configuration in the dropdown and load it into the form
     * 
     * @param string $configName
     */
    public function setSelectedConfiguration(string $configName): void
    {
        if ($this->configDropdown !== null) {
            $this->configDropdown->setSelectedConfiguration($configName);
            $this->selectConfiguration($configName);
        }
    }

    /**
     * Select a configuration and load it into the form
     * 
     * @param string $configName
     */
    public function selectConfiguration(string $configName): void
    {
        if ($this->requestOverview !== null && $this->configDropdown !== null) {
            // Load configuration from dropdown selection
            $config = $this->configDropdown->getSelectedConfigurationData($configName);
            if ($config !== null) {
                $this->requestOverview->updateOverview($config);
                // Save current configuration
                $this->currentConfig = $config;
            } else {
                $this->requestOverview->setDefaultOverview();
                $this->currentConfig = null;
            }
        }
    }

    /**
     * Get the configuration form instance
     * 
     * @return ConfigurationForm|null
     */
    public function getConfigurationForm(): ?ConfigurationForm
    {
        return $this->configForm;
    }

    /**
     * Get the results display instance
     * 
     * @return ResultsDisplay|null
     */
    public function getResultsDisplay(): ?ResultsDisplay
    {
        return $this->resultsDisplay;
    }

    /**
     * Get the configuration dropdown instance
     * 
     * @return ConfigurationDropdown|null
     */
    public function getConfigurationDropdown(): ?ConfigurationDropdown
    {
        return $this->configDropdown;
    }

    /**
     * Start the test with the current configuration
     */
    private function startTest(): void
    {
        try {
            // Check if a configuration is selected
            if ($this->currentConfig === null) {
                if ($this->statusLabel !== null) {
                    Label::setText($this->statusLabel, "请先选择一个配置");
                }
                return;
            }

            // Validate that oha is available
            if (!$this->commandBuilder->isOhaAvailable()) {
                if ($this->statusLabel !== null) {
                    Label::setText($this->statusLabel, "OHA binary not found. Please install oha first.");
                }
                return;
            }

            // Disable start button and enable stop button
            if ($this->startButton !== null) {
                Control::disable($this->startButton);
            }
            if ($this->stopButton !== null) {
                Control::enable($this->stopButton);
            }

            // Show indeterminate progress bar
            if ($this->progressBar !== null) {
                ProgressBar::setValue($this->progressBar, -1);
                Control::show($this->progressBar);
            }

            // Build the command
            $command = $this->commandBuilder->buildCommand($this->currentConfig);
            
            // Clear previous results and show test running status
            if ($this->statusLabel !== null) {
                Label::setText($this->statusLabel, "Test running...");
            }
            
            // Clear output
            if ($this->outputEntry !== null) {
                $this->currentOutput = "Starting test..." . PHP_EOL . PHP_EOL . "Test is running. Please wait for completion." . PHP_EOL . "Note: oha tool outputs results only after completion." . PHP_EOL;
                MultilineEntry::setText($this->outputEntry, $this->currentOutput);
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

            // Set up callbacks for real-time output and completion
            $outputCallback = function($output) {
                // Append output to the output entry
                if ($this->outputEntry !== null) {
                    $this->currentOutput .= $output;
                    MultilineEntry::setText($this->outputEntry, $this->currentOutput);
                }
            };
            
            $completionCallback = function($testResult) {
                // Parse the result using ResultParser for better metrics
                $parsedResult = $this->resultParser->parseOutput($testResult->rawOutput);
                
                // Update status
                if ($this->statusLabel !== null) {
                    Label::setText($this->statusLabel, "Test completed");
                }

                // Update metrics
                if ($this->requestsPerSecLabel !== null) {
                    Label::setText($this->requestsPerSecLabel, number_format($parsedResult->requestsPerSecond, 2));
                }

                if ($this->totalRequestsLabel !== null) {
                    Label::setText($this->totalRequestsLabel, number_format($parsedResult->totalRequests));
                }

                if ($this->successRateLabel !== null) {
                    Label::setText($this->successRateLabel, number_format($parsedResult->successRate, 2) . '%');
                }

                if ($this->performanceLabel !== null) {
                    // Simple performance rating based on requests per second and success rate
                    $rps = $parsedResult->requestsPerSecond;
                    $successRate = $parsedResult->successRate;
                    $performance = "Poor";
                    if ($successRate >= 95 && $rps >= 500) {
                        $performance = "Excellent";
                    } elseif ($successRate >= 95 && $rps >= 100) {
                        $performance = "Very Good";
                    } elseif ($successRate >= 95 && $rps >= 50) {
                        $performance = "Good";
                    } elseif ($successRate >= 95 && $rps >= 10) {
                        $performance = "Fair";
                    } elseif ($successRate >= 95) {
                        $performance = "Poor";
                    } else {
                        $performance = "Poor (Low Success Rate)";
                    }
                    Label::setText($this->performanceLabel, $performance);
                }

                // Display the raw output in the test output area
                if ($this->outputEntry !== null) {
                    $this->currentOutput = $testResult->rawOutput;
                    MultilineEntry::setText($this->outputEntry, $this->currentOutput);
                }
                
                // Enable save button
                if ($this->saveButton !== null) {
                    Control::enable($this->saveButton);
                }

                // Re-enable start button and disable stop button
                if ($this->startButton !== null) {
                    Control::enable($this->startButton);
                }
                if ($this->stopButton !== null) {
                    Control::disable($this->stopButton);
                }
                
                // Hide progress bar
                if ($this->progressBar !== null) {
                    ProgressBar::setValue($this->progressBar, 0);
                    Control::hide($this->progressBar);
                }
            };
            
            // Start the test
            $this->testExecutor->executeTest($command, $this->currentConfig, $outputCallback, $completionCallback);
            
        } catch (Exception $e) {
            // Re-enable start button and disable stop button on error
            if ($this->startButton !== null) {
                Control::enable($this->startButton);
            }
            if ($this->stopButton !== null) {
                Control::disable($this->stopButton);
            }
            
            // Hide progress bar
            if ($this->progressBar !== null) {
                ProgressBar::setValue($this->progressBar, 0);
                Control::hide($this->progressBar);
            }
            
            // Show error message
            if ($this->statusLabel !== null) {
                Label::setText($this->statusLabel, "Error: " . $e->getMessage());
            }
            if ($this->outputEntry !== null) {
                $this->currentOutput = "Error: " . $e->getMessage() . PHP_EOL;
                MultilineEntry::setText($this->outputEntry, $this->currentOutput);
            }
        }
    }

    /**
     * Stop the currently running test
     */
    private function stopTest(): void
    {
        try {
            if ($this->testExecutor->stopTest()) {
                // Update status
                if ($this->statusLabel !== null) {
                    Label::setText($this->statusLabel, "Test stopped");
                }
                
                // Append stop message to output
                if ($this->outputEntry !== null) {
                    $this->currentOutput .= PHP_EOL . "--- Test stopped by user ---" . PHP_EOL;
                    MultilineEntry::setText($this->outputEntry, $this->currentOutput);
                }
                
                // Re-enable start button and disable stop button
                if ($this->startButton !== null) {
                    Control::enable($this->startButton);
                }
                if ($this->stopButton !== null) {
                    Control::disable($this->stopButton);
                }
                
                // Hide progress bar
                if ($this->progressBar !== null) {
                    ProgressBar::setValue($this->progressBar, 0);
                    Control::hide($this->progressBar);
                }
            }
        } catch (Exception $e) {
            if ($this->statusLabel !== null) {
                Label::setText($this->statusLabel, "Failed to stop test: " . $e->getMessage());
            }
            if ($this->outputEntry !== null) {
                $this->currentOutput .= "Failed to stop test: " . $e->getMessage() . PHP_EOL;
                MultilineEntry::setText($this->outputEntry, $this->currentOutput);
            }
        }
    }

    /**
     * Save configuration with user input for name
     * 
     * @param TestConfiguration $config
     */
    private function saveConfiguration($config): void
    {
        try {
            // For now, generate a simple name based on URL and timestamp
            // In a full implementation, this would show a dialog for user input
            $urlParts = parse_url($config->url);
            $host = $urlParts['host'] ?? 'unknown';
            $timestamp = date('Y-m-d_H-i-s');
            $configName = $host . '_' . $timestamp;
            
            // Save the configuration
            $success = $this->configManager->saveConfiguration($configName, $config);
            
            if ($success) {
                $this->configForm->clearError();
                $this->refreshConfigurationDropdown();
                
                // Also refresh the management window table if it exists
                if ($this->configManagerWindow !== null) {
                    $this->configManagerWindow->refreshTable();
                }
                
                // Show success message in results display temporarily
                $this->resultsDisplay->updateStatus("Configuration saved as: " . $configName);
            } else {
                $this->configForm->showError("Failed to save configuration");
            }
            
        } catch (Exception $e) {
            $this->configForm->showError("Error saving configuration: " . $e->getMessage());
        }
    }

    /**
     * Update method to be called periodically for test execution monitoring
     * This should be called from the main application event loop
     */
    public function update(): void
    {
        // Only update if a test is actually running
        if ($this->testExecutor !== null && $this->testExecutor->isRunning()) {
            $this->testExecutor->update();
        }
    }

    /**
     * Cleanup resources
     */
    public function cleanup(): void
    {
        try {
            // Stop any running tests first
            if ($this->testExecutor !== null && $this->testExecutor->isRunning()) {
                $this->testExecutor->stopTest();
            }

            // Cleanup child components in reverse order of creation
            // This ensures proper libui control lifecycle management
            if ($this->configManagerWindow !== null) {
                $this->configManagerWindow->cleanup();
                $this->configManagerWindow = null;
            }

            if ($this->resultsDisplay !== null) {
                $this->resultsDisplay->cleanup();
                $this->resultsDisplay = null;
            }

            if ($this->requestOverview !== null) {
                $this->requestOverview->cleanup();
                $this->requestOverview = null;
            }

            if ($this->configForm !== null) {
                $this->configForm->cleanup();
                $this->configForm = null;
            }

            if ($this->configDropdown !== null) {
                $this->configDropdown->cleanup();
                $this->configDropdown = null;
            }

            // Clear UI element references
            $this->startButton = null;
            $this->stopButton = null;
            $this->progressBar = null;
            
            // Clear UI element references
            $this->statusLabel = null;
            $this->requestsPerSecLabel = null;
            $this->totalRequestsLabel = null;
            $this->successRateLabel = null;
            $this->performanceLabel = null;
            $this->outputEntry = null;
            $this->saveButton = null;
            
            // Clear current output
            $this->currentOutput = "";
            
            // Clear current configuration
            $this->currentConfig = null;

            // Clear layout references
            $this->vbox = null;

            // Cleanup core components
            $this->testExecutor = null;
            $this->commandBuilder = null;
            $this->resultParser = null;
            $this->configManager = null;

            // Cleanup window resources last
            if ($this->window !== null) {
//                Control::destroy($this->window);
                $this->window = null;
            }

        } catch (\Throwable $e) {
            error_log("MainWindow cleanup error: " . $e->getMessage());
        }
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->cleanup();
    }
}