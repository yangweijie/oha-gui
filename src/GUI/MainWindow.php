<?php

namespace OhaGui\GUI;

use Kingbes\Libui\App;
use Kingbes\Libui\Menu;
use Kingbes\Libui\Window;
use Kingbes\Libui\Box;
use Kingbes\Libui\Control;
use FFI\CData;
use Kingbes\Libui\Window\MsgBox;
use OhaGui\Core\TestExecutor;
use OhaGui\Core\OhaCommandBuilder;
use OhaGui\Core\ResultParser;
use OhaGui\Core\ConfigurationManager;
use OhaGui\Models\TestConfiguration;
use OhaGui\Utils\UserGuidance;
use Exception;

/**
 * Main Window class for the OHA GUI application
 * 
 * Creates and manages the main application window with proper sizing and layout
 */
class MainWindow
{
    private CData $window;
    private CData $mainBox;
    private ?ConfigurationForm $configForm = null;

    private ?ResultsDisplay $resultsDisplay = null;
    private ?TestExecutor $testExecutor = null;
    private ?OhaCommandBuilder $commandBuilder = null;
    private ?ResultParser $resultParser = null;
    private ?ConfigurationManager $configManager = null;
    private ?ConfigurationManagerWindow $configManagerWindow = null;
    private array $keyboardShortcuts = [];
    private int $minWidth = 500;
    private int $minHeight = 350;
    
    private const WINDOW_WIDTH = 650;
    private const WINDOW_HEIGHT = 550;
    private const WINDOW_TITLE = 'OHA GUI Tool - HTTP Load Testing';

    /**
     * Initialize the main window
     */
    public function __construct()
    {
        $this->testExecutor = new TestExecutor();
        $this->commandBuilder = new OhaCommandBuilder();
        $this->resultParser = new ResultParser();
        $this->configManager = new ConfigurationManager();

        $this->createWindow();
        $this->createLayout();
        $this->setupEventHandlers();
        $this->connectComponents();

        // Setup configuration manager (after window is created)
        $this->setupConfigurationManager();

        // Set application icon if available
        $iconPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'icon.ico';
        $this->setIcon($iconPath);
    }

    /**
     * Create the main window
     * 
     * @return void
     */
    private function createWindow(): void
    {
        // Create window with title, width, height, and no menubar
        $this->window = Window::create(
            self::WINDOW_TITLE,
            self::WINDOW_WIDTH,
            self::WINDOW_HEIGHT,
            0 // No menubar
        );

        // Set window properties
        Window::setMargined($this->window, true);
        
        // Set minimum window size to prevent UI from becoming unusable
        $this->setMinimumSize(500, 350);
        
    }

    /**
     * Create the main layout
     * 
     * @return void
     */
    private function createLayout(): void
    {
        // Create main vertical box for layout
        $this->mainBox = Box::newVerticalBox();
        Box::setPadded($this->mainBox, true);

        // Add a title label
        $titleLabel = \Kingbes\Libui\Label::create('OHA GUI Tool');
        Box::append($this->mainBox, $titleLabel, false);

        // Add some vertical spacing
        $spacer1 = \Kingbes\Libui\Label::create('');
        Box::append($this->mainBox, $spacer1, false);

        // Create a horizontal box to center the content
        $centerBox = Box::newHorizontalBox();
        Box::setPadded($centerBox, true);

        // Left spacer
        $leftSpacer = \Kingbes\Libui\Label::create('');
        Box::append($centerBox, $leftSpacer, true);

        // Center content
        $contentBox = Box::newVerticalBox();
        Box::setPadded($contentBox, true);

        // Create configuration form
        $this->configForm = new ConfigurationForm();
        Box::append($contentBox, $this->configForm->getControl(), false);

        Box::append($centerBox, $contentBox, false);

        // Right spacer
        $rightSpacer = \Kingbes\Libui\Label::create('');
        Box::append($centerBox, $rightSpacer, true);

        Box::append($this->mainBox, $centerBox, true);

        // Add some vertical spacing
        $spacer2 = \Kingbes\Libui\Label::create('');
        Box::append($this->mainBox, $spacer2, false);

        // Create results display
        $this->resultsDisplay = new ResultsDisplay();
        Box::append($this->mainBox, $this->resultsDisplay->getControl(), true);

        // Set the main box as window child
        Window::setChild($this->window, $this->mainBox);
    }

    /**
     * Setup event handlers
     * 
     * @return void
     */
    private function setupEventHandlers(): void
    {
        // Handle window closing event
        Window::onClosing($this->window, function($window) {
            return $this->onClosing();
        });
    }

    /**
     * Connect components together
     * 
     * @return void
     */
    private function connectComponents(): void
    {
        // Connect form to test execution
        $this->configForm->setOnStartTestCallback(function(TestConfiguration $config) {
            $this->startTest($config);
        });

        $this->configForm->setOnStopTestCallback(function() {
            $this->stopTest();
        });

        // Connect form configuration management
        $this->configForm->setOnSaveConfigCallback(function($name, $config, $wasUpdate) {
            $this->onConfigurationSaved($name, $config, $wasUpdate);
        });

        $this->configForm->setOnLoadConfigCallback(function(TestConfiguration $config) {
            $this->onConfigurationLoaded($config);
        });

        // Connect form to configuration manager window
        $this->configForm->setOnManageConfigCallback(function() {
            $this->showConfigurationManager();
        });

        // Connect results display save functionality
        $this->resultsDisplay->setOnSaveResultsCallback(function($result) {
            $this->saveTestResults($result);
        });
    }

    /**
     * Handle window closing event
     * 
     * @return int 1 to allow closing, 0 to prevent closing
     */
    private function onClosing(): int
    {
        // Check if test is running and ask for confirmation
        if ($this->testExecutor !== null && $this->testExecutor->isRunning()) {
            try {
                if (class_exists('Kingbes\Libui\Window\MsgBox')) {
                    $response = MsgBox::question(
                        $this->window,
                        'Test Running',
                        'A test is currently running. Do you want to stop it and exit?'
                    );
                    
                    if ($response === 0) { // User chose "No"
                        return 0; // Prevent closing
                    }
                }
                
                // Stop the running test
                $this->testExecutor->stopTest();
            } catch (Exception $e) {
                error_log("Error stopping test during window close: " . $e->getMessage());
            }
        }

        // Perform resource cleanup
        $this->performResourceCleanup();
        
        // Allow window to close
        return 1;
    }

    /**
     * Show the main window
     * 
     * @return void
     */
    public function show(): void
    {
        Control::show($this->window);
    }

    /**
     * Hide the main window
     * 
     * @return void
     */
    public function hide(): void
    {
        Control::hide($this->window);
    }

    /**
     * Destroy the main window and clean up resources
     * 
     * @return void
     */
    public function destroy(): void
    {
        if (isset($this->window)) {
            Control::destroy($this->window);
        }
    }

    /**
     * Get the window handle
     * 
     * @return CData
     */
    public function getWindow(): CData
    {
        return $this->window;
    }

    /**
     * Get the configuration form
     * 
     * @return ConfigurationForm|null
     */
    public function getConfigurationForm(): ?ConfigurationForm
    {
        return $this->configForm;
    }



    /**
     * Get the results display
     * 
     * @return ResultsDisplay|null
     */
    public function getResultsDisplay(): ?ResultsDisplay
    {
        return $this->resultsDisplay;
    }

    /**
     * Set window title
     * 
     * @param string $title
     * @return void
     */
    public function setTitle(string $title): void
    {
        Window::setTitle($this->window, $title);
    }

    /**
     * Set window size
     * 
     * @param int $width
     * @param int $height
     * @return void
     */
    public function setSize(int $width, int $height): void
    {
        Window::setContentSize($this->window, $width, $height);
    }

    /**
     * Start test execution
     * 
     * @param TestConfiguration $config
     * @return void
     */
    private function startTest(TestConfiguration $config): void
    {
        try {
            // Validate configuration
            $errors = $config->validate();
            if (!empty($errors)) {
                $guidance = UserGuidance::getErrorGuidance('validation_failed', implode(', ', $errors));
                $this->showUserFriendlyError($guidance['title'], $guidance['message'], $guidance['suggestions']);
                if ($this->configForm !== null) {
                    $this->configForm->enableTestControls();
                }
                return;
            }

            // Check if oha binary is available
            if ($this->commandBuilder === null || !$this->commandBuilder->isOhaAvailable()) {
                $guidance = UserGuidance::getErrorGuidance('oha_not_found');
                $this->showUserFriendlyError($guidance['title'], $guidance['message'], $guidance['suggestions']);
                if ($this->configForm !== null) {
                    $this->configForm->enableTestControls();
                }
                return;
            }

            $command = $this->commandBuilder->buildCommand($config);
            
            // Clear previous results and show test starting
            if ($this->resultsDisplay === null || $this->testExecutor === null) {
                throw new Exception('GUI components not initialized');
            }
            
            $this->resultsDisplay->clearOutput();
            $this->resultsDisplay->showTestStarting();
            $this->resultsDisplay->appendOutput('Command: ' . $command . "\n");
            $this->resultsDisplay->appendOutput("Starting test at " . date('Y-m-d H:i:s') . "\n");
            
            // Start test execution with proper GUI thread updates
            $this->testExecutor->executeTest(
                $command,
                function($output) {
                    // Real-time output callback - use queueMain for thread-safe GUI updates
                    App::queueMain(function($data) use ($output) {
                        if ($this->resultsDisplay !== null) {
                            $this->resultsDisplay->appendOutput($output);
                        }
                    });
                },
                function($error) {
                    // Enhanced error callback with guidance - use queueMain for thread-safe GUI updates
                    App::queueMain(function($data) use ($error) {
                        $this->handleTestExecutionError($error);
                    });
                },
                function($exitCode, $error = null) {
                    // Completion callback - use queueMain for thread-safe GUI updates
                    App::queueMain(function($data) use ($exitCode, $error) {
                        $this->onTestCompleted($exitCode, $error);
                    });
                }
            );
            
            // Start monitoring test progress
            $this->startTestProgressMonitoring();
            
        } catch (Exception $e) {
            $guidance = UserGuidance::getErrorGuidance('oha_execution_failed', $e->getMessage());
            $this->showUserFriendlyError($guidance['title'], $guidance['message'], $guidance['suggestions']);
            if ($this->configForm !== null) {
                $this->configForm->enableTestControls();
            }
        }
    }

    /**
     * Stop test execution
     * 
     * @return void
     */
    private function stopTest(): void
    {
        try {
            if ($this->testExecutor !== null && $this->testExecutor->isRunning()) {
                $this->testExecutor->stopTest();
                if ($this->resultsDisplay !== null) {
                    $this->resultsDisplay->showTestStopped();
                    $this->resultsDisplay->appendOutput("\nTest stopped by user.\n");
                }
            }
        } catch (Exception $e) {
            if ($this->resultsDisplay !== null) {
                $this->resultsDisplay->showTestError('Error stopping test: ' . $e->getMessage());
            }
        } finally {
            if ($this->configForm !== null) {
                $this->configForm->enableTestControls();
            }
        }
    }

    /**
     * Handle test completion
     * 
     * @param int $exitCode
     * @param array|null $error Optional error information
     * @return void
     */
    private function onTestCompleted(int $exitCode, ?array $error = null): void
    {
        // Set progress bar to 100% completion
        if ($this->resultsDisplay !== null) {
            $this->resultsDisplay->updateProgress(100);
        }
        
        if ($this->configForm !== null) {
            $this->configForm->enableTestControls();
        }
        $completionTime = date('Y-m-d H:i:s');
        
        if ($exitCode === 0) {
            // Parse results
            if ($this->testExecutor === null || $this->resultParser === null || $this->resultsDisplay === null) {
                return;
            }
            
            $output = $this->testExecutor->getOutput();
            try {
                $result = $this->resultParser->parseOutput($output);
                $this->resultsDisplay->updateMetrics($result);
                $this->resultsDisplay->showTestCompleted();
                
                // Display comprehensive completion summary
                $this->resultsDisplay->appendOutput("\n" . str_repeat("=", 50) . "\n");
                $this->resultsDisplay->appendOutput("✓ TEST COMPLETED SUCCESSFULLY\n");
                $this->resultsDisplay->appendOutput("Completed at: {$completionTime}\n");
                $this->resultsDisplay->appendOutput(str_repeat("=", 50) . "\n");
                
                // Display key metrics prominently
                $this->resultsDisplay->appendOutput("📊 PERFORMANCE METRICS:\n");
                $this->resultsDisplay->appendOutput("• Requests/sec: {$result->requestsPerSecond}\n");
                $this->resultsDisplay->appendOutput("• Total requests: {$result->totalRequests}\n");
                $this->resultsDisplay->appendOutput("• Success rate: {$result->successRate}%\n");
                if ($result->failedRequests > 0) {
                    $this->resultsDisplay->appendOutput("• Failed requests: {$result->failedRequests}\n");
                }
                $this->resultsDisplay->appendOutput("\n");
                
                // Add performance interpretation
                $this->addPerformanceInterpretation($result);
                
            } catch (Exception $e) {
                if ($this->resultsDisplay !== null) {
                    $this->resultsDisplay->showTestError('Failed to parse results: ' . $e->getMessage());
                    $this->resultsDisplay->appendOutput("\n❌ Result parsing failed, but raw output is available above.\n");
                    $this->resultsDisplay->appendOutput("Completed at: {$completionTime}\n\n");
                }
            }
        } else {
            if ($this->resultsDisplay !== null) {
                $this->resultsDisplay->showTestError('Test failed with exit code: ' . $exitCode);
                $this->resultsDisplay->appendOutput("\n❌ TEST FAILED\n");
                $this->resultsDisplay->appendOutput("Exit code: {$exitCode}\n");
                $this->resultsDisplay->appendOutput("Completed at: {$completionTime}\n");
            }
            
            // Provide guidance based on exit code
            $this->provideExitCodeGuidance($exitCode);
        }
    }

    /**
     * Handle configuration loaded event
     *
     * @param TestConfiguration $config
     * @return void
     */
    private function onConfigurationLoaded(TestConfiguration $config): void
    {
        try {
            if ($this->resultsDisplay === null) {
                return;
            }

            $this->resultsDisplay->appendOutput("✓ Configuration '{$config->name}' loaded successfully\n");
            $this->resultsDisplay->appendOutput("  URL: {$config->url}\n");
            $this->resultsDisplay->appendOutput("  Method: {$config->method}\n");
            $this->resultsDisplay->appendOutput("  Connections: {$config->concurrentConnections}\n");
            $this->resultsDisplay->appendOutput("  Duration: {$config->duration}s\n");
            if (!empty($config->headers)) {
                $this->resultsDisplay->appendOutput("  Headers: " . count($config->headers) . " defined\n");
            }
            if (!empty($config->body)) {
                $this->resultsDisplay->appendOutput("  Body: " . strlen($config->body) . " characters\n");
            }
            $this->resultsDisplay->appendOutput("\n");

            // Enable the start button since we have a valid configuration
            if ($this->configForm !== null) {
                $this->configForm->enableTestControls();
            }
        } catch (Exception $e) {
            $guidance = UserGuidance::getErrorGuidance('config_load_failed', $e->getMessage());
            $this->showUserFriendlyError($guidance['title'], $guidance['message'], $guidance['suggestions']);
        }
    }

    /**
     * Handle configuration saved event
     *
     * @param string $name Configuration name
     * @param TestConfiguration $config Configuration object
     * @param bool $wasUpdate Whether this was an update or new save
     * @return void
     */
    private function onConfigurationSaved(string $name, TestConfiguration $config, bool $wasUpdate): void
    {
        try {
            if ($this->resultsDisplay === null) {
                return;
            }

            $action = $wasUpdate ? 'updated' : 'saved';
            $this->resultsDisplay->appendOutput("✓ Configuration {$action} as '{$name}'\n");
            $this->resultsDisplay->appendOutput("  URL: {$config->url}\n");
            $this->resultsDisplay->appendOutput("  Method: {$config->method}\n");
            $this->resultsDisplay->appendOutput("  Connections: {$config->concurrentConnections}\n");
            $this->resultsDisplay->appendOutput("  Duration: {$config->duration}s\n\n");

            // Refresh form configuration lists
            if ($this->configForm !== null) {
                $this->configForm->refreshConfigurationLists();
            }

            // Enable the start button since we have a valid configuration
            if ($this->configForm !== null) {
                $this->configForm->enableTestControls();
            }
        } catch (Exception $e) {
            error_log("Error handling configuration saved event: " . $e->getMessage());
        }
    }





    /**
     * Save test results to file
     * 
     * @param mixed $result
     * @return void
     */
    private function saveTestResults($result): void
    {
        try {
            // For now, we'll save to a simple text file
            // In a real implementation, you'd show a file dialog
            $filename = 'test_results_' . date('Y-m-d_H-i-s') . '.txt';
            $content = $result->getFormattedSummary() . "\n\n" . $this->testExecutor->getOutput();
            
            if (file_put_contents($filename, $content)) {
                $this->resultsDisplay->appendOutput("Results saved to '{$filename}'.\n");
            } else {
                $this->resultsDisplay->showTestError('Failed to save results to file.');
            }
        } catch (Exception $e) {
            $this->resultsDisplay->showTestError('Error saving results: ' . $e->getMessage());
        }
    }

    /**
     * Start monitoring test progress
     * 
     * @return void
     */
    private function startTestProgressMonitoring(): void
    {
        // Start periodic progress updates using queueMain
        $this->scheduleProgressUpdate();
    }

    /**
     * Schedule next progress update
     * 
     * @return void
     */
    private function scheduleProgressUpdate(): void
    {
        App::queueMain(function($data) {
            $this->updateTestProgress();
        });
    }

    /**
     * Update test progress and schedule next update if needed
     * 
     * @return void
     */
    private function updateTestProgress(): void
    {
        if ($this->testExecutor === null) {
            return;
        }

        if ($this->testExecutor->isRunning()) {
            // Update progress bar to show activity (indeterminate mode)
            if ($this->resultsDisplay !== null) {
                $this->resultsDisplay->updateProgress(-1); // Indeterminate progress
            }
            
            // Schedule next update in 500ms using a proper timer
            $this->scheduleNextProgressUpdate();
        } else {
            // Test completed, set progress to 100%
            if ($this->resultsDisplay !== null) {
                $this->resultsDisplay->updateProgress(100);
            }
        }
    }

    /**
     * Schedule next progress update with delay
     * 
     * @return void
     */
    private function scheduleNextProgressUpdate(): void
    {
        // Use App::queueMain with a delay for periodic updates
        App::queueMain(function($data) {
            // Update progress immediately
            $this->updateTestProgress();
            
            // If test is still running, schedule next update
            if ($this->testExecutor !== null && $this->testExecutor->isRunning()) {
                // Schedule next update after 500ms
                // Note: This is a conceptual implementation. In a real implementation,
                // you might need to use a timer mechanism provided by the underlying system.
                // For now, we'll use a simple approach with a flag to prevent infinite recursion.
                static $scheduling = false;
                if (!$scheduling) {
                    $scheduling = true;
                    // Schedule next update after a short delay
                    App::queueMain(function($data) use (&$scheduling) {
                        $scheduling = false;
                        if ($this->testExecutor !== null && $this->testExecutor->isRunning()) {
                            $this->scheduleNextProgressUpdate();
                        }
                    });
                }
            }
        });
    }

    /**
     * Update test execution status (should be called periodically)
     * 
     * @return void
     */
    public function updateTestStatus(): void
    {
        if ($this->testExecutor->isRunning()) {
            // Test is still running, the callbacks will handle output
            // This method can be used for periodic UI updates if needed
        }
    }

    /**
     * Get test executor instance
     * 
     * @return TestExecutor|null
     */
    public function getTestExecutor(): ?TestExecutor
    {
        return $this->testExecutor;
    }

    /**
     * Check if test is currently running
     * 
     * @return bool
     */
    public function isTestRunning(): bool
    {
        return $this->testExecutor !== null && $this->testExecutor->isRunning();
    }

    /**
     * Set minimum window size
     * 
     * @param int $width
     * @param int $height
     * @return void
     */
    private function setMinimumSize(int $width, int $height): void
    {
        try {
            // Store minimum size for validation during resize events
            $this->minWidth = $width;
            $this->minHeight = $height;
            
            // If libui supports minimum size constraints, apply them
            if (method_exists('Kingbes\Libui\Window', 'setMinSize')) {
                \Kingbes\Libui\Window::setMinSize($this->window, $width, $height);
            } else {
                // Fallback: Monitor window size changes and enforce minimum
                $this->enforceMinimumSize();
            }
        } catch (Exception $e) {
            error_log("Could not set minimum window size: " . $e->getMessage());
        }
    }

    /**
     * Center the window on screen
     * 
     * @return void
     */
    private function centerWindow(): void
    {
        try {
            // If libui supports window positioning, center the window
            if (method_exists('Kingbes\Libui\Window', 'setPosition')) {
                // Get screen dimensions (this would need platform-specific implementation)
                $screenWidth = $this->getScreenWidth();
                $screenHeight = $this->getScreenHeight();
                
                if ($screenWidth > 0 && $screenHeight > 0) {
                    $x = ($screenWidth - self::WINDOW_WIDTH) / 2;
                    $y = ($screenHeight - self::WINDOW_HEIGHT) / 2;
                    
                    \Kingbes\Libui\Window::setPosition($this->window, (int)$x, (int)$y);
                }
            } else {
                // Fallback: libui may center windows by default on some platforms
                // Log that centering is not available
                error_log("Window centering not supported by current libui version");
            }
        } catch (Exception $e) {
            error_log("Could not center window: " . $e->getMessage());
        }
    }

    /**
     * Set application icon and window properties
     * 
     * @param string $iconPath
     * @return void
     */
    public function setIcon(string $iconPath): void
    {
        // Set window icon if supported by libui
        if (file_exists($iconPath)) {
            try {
                // libui may not support icons directly, but we can try
                // This would be platform-specific implementation
                if (method_exists('Kingbes\Libui\Window', 'setIcon')) {
                    \Kingbes\Libui\Window::setIcon($this->window, $iconPath);
                }
            } catch (Exception $e) {
                error_log("Could not set window icon: " . $e->getMessage());
            }
        }
        
        // Set additional window properties for better user experience
        $this->setWindowProperties();
    }

    /**
     * Set additional window properties for better user experience
     * 
     * @return void
     */
    private function setWindowProperties(): void
    {
        try {
            // Set window to be resizable
            if (method_exists('Kingbes\Libui\Window', 'setResizable')) {
                \Kingbes\Libui\Window::setResizable($this->window, true);
            }
            
            // Set window to appear in taskbar
            if (method_exists('Kingbes\Libui\Window', 'setTaskbarVisible')) {
                \Kingbes\Libui\Window::setTaskbarVisible($this->window, true);
            }
            
            // Set window focus behavior
            if (method_exists('Kingbes\Libui\Window', 'setFocusable')) {
                \Kingbes\Libui\Window::setFocusable($this->window, true);
            }
            
        } catch (Exception $e) {
            error_log("Could not set window properties: " . $e->getMessage());
        }
    }

    /**
     * Setup configuration manager window
     *
     * @return void
     */
    private function setupConfigurationManager(): void
    {
        try {
            // Create configuration manager window
            $this->configManagerWindow = new ConfigurationManagerWindow();
            $this->configManagerWindow->setOnCloseCallback(function($selectedConfigName = null, $selectedConfig = null) {
                // Refresh the configuration list in the main form when the manager is closed
                if ($this->configForm) {
                    $this->configForm->refreshConfigurationLists();
                    
                    // If a configuration was selected, load it into the form
                    if ($selectedConfigName && $selectedConfig) {
                        // Set the selected configuration name in the combobox
                        $this->configForm->setSelectedConfiguration($selectedConfigName);
                        
                        // Trigger the configuration loaded callback
                        if ($this->configForm && method_exists($this->configForm, 'onConfigurationSelected')) {
                            // This would require adding a public method to ConfigurationForm
                        }
                    }
                }
            });

        } catch (Exception $e) {
            error_log("Error setting up configuration manager: " . $e->getMessage());
        }
    }

    /**
     * Create menu system if supported by libui
     * 
     * @return void
     */
    private function createMenuSystem(): void
    {
        try {
            // If libui supports menus
            if (class_exists('Kingbes\Libui\Menu')) {
                $this->createApplicationMenu();
            } else {
                error_log("Menu system not supported by current libui version");
            }
        } catch (Exception $e) {
            error_log("Could not create menu system: " . $e->getMessage());
        }
    }

    /**
     * Create application menu
     * 
     * @return void
     */
    private function createApplicationMenu(): void
    {
        try {
            // File menu
            $fileMenu = Menu::create('File');
            Menu::appendItem($fileMenu, 'New Configuration', function() {
                $this->clearConfigurationForm();
            });
            Menu::appendItem($fileMenu, 'Load Configuration...', function() {
                $this->focusConfigurationForm();
            });
            Menu::appendSeparator($fileMenu);
            Menu::appendItem($fileMenu, 'Export Results...', function() {
                $this->exportResults();
            });
            Menu::appendSeparator($fileMenu);
            Menu::appendQuitItem($fileMenu);

            // Test menu
            $testMenu = Menu::create('Test');
            Menu::appendItem($testMenu, 'Start Test', function() {
                $this->startTestFromShortcut();
            });
            Menu::appendItem($testMenu, 'Stop Test', function() {
                $this->stopTest();
            });
            Menu::appendSeparator($testMenu);
            Menu::appendItem($testMenu, 'Clear Results', function() {
                $this->clearResults();
            });

            // Help menu
            $helpMenu = Menu::create('Help');
            Menu::appendItem($helpMenu, 'Keyboard Shortcuts', function() {
                $this->showKeyboardShortcutsHelp();
            });
            Menu::appendItem($helpMenu, 'About OHA GUI Tool', function() {
                $this->showAboutDialog();
            });
            
        } catch (Exception $e) {
            error_log("Could not create application menu: " . $e->getMessage());
        }
    }

    /**
     * Perform comprehensive resource cleanup
     * 
     * @return void
     */
    public function performResourceCleanup(): void
    {
        try {
            // Stop any running tests
            if (isset($this->testExecutor) && $this->testExecutor->isRunning()) {
                try {
                    $this->testExecutor->stopTest();
                } catch (Exception $e) {
                    error_log("Error stopping test during cleanup: " . $e->getMessage());
                }
            }

            // Clean up test executor resources
            if (isset($this->testExecutor)) {
                try {
                    $this->testExecutor->cleanupResources();
                } catch (Exception $e) {
                    error_log("Error cleaning up test executor: " . $e->getMessage());
                }
            }

            // Clean up GUI components with individual error handling
            if (isset($this->configForm)) {
                try {
                    $this->configForm->cleanup();
                } catch (Exception $e) {
                    error_log("Error cleaning up configuration form: " . $e->getMessage());
                }
            }

            if (isset($this->resultsDisplay)) {
                try {
                    $this->resultsDisplay->cleanup();
                } catch (Exception $e) {
                    error_log("Error cleaning up results display: " . $e->getMessage());
                }
            }

            // Clear references to prevent memory leaks
            $this->configForm = null;
            $this->resultsDisplay = null;
            $this->testExecutor = null;
            $this->commandBuilder = null;
            $this->resultParser = null;
            $this->configManager = null;

            // Destroy window (this should be done last)
            try {
                $this->destroy();
            } catch (Exception $e) {
                error_log("Error destroying window: " . $e->getMessage());
            }

        } catch (Exception $e) {
            error_log("Critical error during resource cleanup: " . $e->getMessage());
        }
    }

    /**
     * Show comprehensive error message with user guidance
     * 
     * @param string $title
     * @param string $message
     * @param array $suggestions
     * @return void
     */
    public function showUserFriendlyError(string $title, string $message, array $suggestions = []): void
    {
        $fullMessage = $message;
        
        if (!empty($suggestions)) {
            $fullMessage .= "\n\nSuggestions:\n";
            foreach ($suggestions as $suggestion) {
                $fullMessage .= "• " . $suggestion . "\n";
            }
        }

        // Display error in results area
        if ($this->resultsDisplay !== null) {
            $this->resultsDisplay->displayError($fullMessage);
        }
        
        // Also try to show a message box if available
        try {
            if (class_exists('Kingbes\Libui\Window\MsgBox')) {
                MsgBox::error($this->window, $title, $fullMessage);
            }
        } catch (Exception $e) {
            // If message box fails, the error is already shown in results area
        }
    }

    /**
     * Handle test execution errors with user guidance
     * 
     * @param string $error
     * @return void
     */
    private function handleTestExecutionError(string $error): void
    {
        // Determine error type based on error message
        $errorType = 'oha_execution_failed';
        
        if (strpos($error, 'Connection refused') !== false || strpos($error, 'timeout') !== false) {
            $errorType = 'network_error';
        } elseif (strpos($error, 'Permission denied') !== false) {
            $errorType = 'permission_error';
        } elseif (strpos($error, 'not found') !== false || strpos($error, 'No such file') !== false) {
            $errorType = 'oha_not_found';
        }
        
        $guidance = UserGuidance::getErrorGuidance($errorType, $error);
        $this->showUserFriendlyError($guidance['title'], $guidance['message'], $guidance['suggestions']);
    }

    /**
     * Get screen width (platform-specific implementation)
     * 
     * @return int Screen width in pixels
     */
    private function getScreenWidth(): int
    {
        try {
            // Platform-specific screen width detection
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows implementation
                $output = shell_exec('wmic desktopmonitor get screenwidth /value 2>nul');
                if ($output && preg_match('/ScreenWidth=(\d+)/', $output, $matches)) {
                    return (int)$matches[1];
                }
            } elseif (PHP_OS_FAMILY === 'Linux') {
                // Linux implementation using xrandr
                $output = shell_exec('xrandr --current 2>/dev/null | grep "primary" | cut -d" " -f4 | cut -d"x" -f1');
                if ($output && is_numeric(trim($output))) {
                    return (int)trim($output);
                }
            } elseif (PHP_OS_FAMILY === 'Darwin') {
                // macOS implementation
                $output = shell_exec('system_profiler SPDisplaysDataType | grep Resolution | head -1 | awk \'{print $2}\'');
                if ($output && is_numeric(trim($output))) {
                    return (int)trim($output);
                }
            }
        } catch (Exception $e) {
            error_log("Could not detect screen width: " . $e->getMessage());
        }
        
        // Fallback to common screen width
        return 1920;
    }

    /**
     * Get screen height (platform-specific implementation)
     * 
     * @return int Screen height in pixels
     */
    private function getScreenHeight(): int
    {
        try {
            // Platform-specific screen height detection
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows implementation
                $output = shell_exec('wmic desktopmonitor get screenheight /value 2>nul');
                if ($output && preg_match('/ScreenHeight=(\d+)/', $output, $matches)) {
                    return (int)$matches[1];
                }
            } elseif (PHP_OS_FAMILY === 'Linux') {
                // Linux implementation using xrandr
                $output = shell_exec('xrandr --current 2>/dev/null | grep "primary" | cut -d" " -f4 | cut -d"x" -f2 | cut -d"+" -f1');
                if ($output && is_numeric(trim($output))) {
                    return (int)trim($output);
                }
            } elseif (PHP_OS_FAMILY === 'Darwin') {
                // macOS implementation
                $output = shell_exec('system_profiler SPDisplaysDataType | grep Resolution | head -1 | awk \'{print $4}\'');
                if ($output && is_numeric(trim($output))) {
                    return (int)trim($output);
                }
            }
        } catch (Exception $e) {
            error_log("Could not detect screen height: " . $e->getMessage());
        }
        
        // Fallback to common screen height
        return 1080;
    }

    /**
     * Enforce minimum window size constraints
     * 
     * @return void
     */
    private function enforceMinimumSize(): void
    {
        try {
            // This would need to be called periodically or on resize events
            // Since libui may not provide resize events, this is a conceptual implementation
            
            // If libui supports getting current window size
            if (method_exists('Kingbes\Libui\Window', 'getContentSize')) {
                $currentSize = \Kingbes\Libui\Window::getContentSize($this->window);
                $currentWidth = $currentSize['width'] ?? self::WINDOW_WIDTH;
                $currentHeight = $currentSize['height'] ?? self::WINDOW_HEIGHT;
                
                $needsResize = false;
                $newWidth = $currentWidth;
                $newHeight = $currentHeight;
                
                if ($currentWidth < $this->minWidth) {
                    $newWidth = $this->minWidth;
                    $needsResize = true;
                }
                
                if ($currentHeight < $this->minHeight) {
                    $newHeight = $this->minHeight;
                    $needsResize = true;
                }
                
                if ($needsResize) {
                    Window::setContentSize($this->window, $newWidth, $newHeight);
                }
            }
        } catch (Exception $e) {
            error_log("Could not enforce minimum window size: " . $e->getMessage());
        }
    }

    /**
     * Focus on configuration form for loading configurations
     * 
     * @return void
     */
    private function focusConfigurationForm(): void
    {
        try {
            // Refresh the configuration lists in the form and focus on the configuration combobox
            if ($this->configForm) {
                $this->configForm->refreshConfigurationLists();
                // Note: In a full implementation, we would also set focus to the configuration combobox
            }
        } catch (Exception $e) {
            error_log("Could not focus configuration form: " . $e->getMessage());
        }
    }

    /**
     * Clear configuration form for new configuration
     * 
     * @return void
     */
    private function clearConfigurationForm(): void
    {
        try {
            if ($this->configForm) {
                // Create a default configuration
                $defaultConfig = new TestConfiguration(
                    '',
                    'http://localhost:8080',
                    'GET',
                    10,
                    10,
                    30,
                    ['Content-Type' => 'application/json', 'User-Agent' => 'OHA-GUI-Tool'],
                    ''
                );
                $this->configForm->setConfiguration($defaultConfig);
                
                if ($this->resultsDisplay) {
                    $this->resultsDisplay->appendOutput("✓ New configuration created\n");
                }
            }
        } catch (Exception $e) {
            error_log("Could not clear configuration form: " . $e->getMessage());
        }
    }

    /**
     * Start test from keyboard shortcut
     * 
     * @return void
     */
    private function startTestFromShortcut(): void
    {
        try {
            if ($this->configForm && !$this->isTestRunning()) {
                $config = $this->configForm->getConfiguration();
                $this->startTest($config);
            }
        } catch (Exception $e) {
            error_log("Could not start test from shortcut: " . $e->getMessage());
        }
    }

    /**
     * Clear results display
     * 
     * @return void
     */
    private function clearResults(): void
    {
        try {
            if ($this->resultsDisplay) {
                $this->resultsDisplay->clearOutput();
                $this->resultsDisplay->appendOutput("Results cleared.\n");
            }
        } catch (Exception $e) {
            error_log("Could not clear results: " . $e->getMessage());
        }
    }

    /**
     * Export results to file
     * 
     * @return void
     */
    private function exportResults(): void
    {
        try {
            if ($this->testExecutor && $this->testExecutor->getOutput()) {
                $this->saveTestResults($this->testExecutor->getOutput());
            } else {
                if ($this->resultsDisplay) {
                    $this->resultsDisplay->appendOutput("No results to export.\n");
                }
            }
        } catch (Exception $e) {
            error_log("Could not export results: " . $e->getMessage());
        }
    }

    /**
     * Show keyboard shortcuts help dialog
     * 
     * @return void
     */
    private function showKeyboardShortcutsHelp(): void
    {
        try {
            $shortcutsText = "Keyboard Shortcuts:\n\n";
            foreach ($this->keyboardShortcuts as $shortcut => $description) {
                $shortcutsText .= sprintf("%-15s %s\n", $shortcut, $description);
            }
            
            if (class_exists('Kingbes\Libui\Window\MsgBox')) {
                MsgBox::info($this->window, 'Keyboard Shortcuts', $shortcutsText);
            } else {
                // Fallback: display in results area
                if ($this->resultsDisplay) {
                    $this->resultsDisplay->appendOutput("\n" . $shortcutsText . "\n");
                }
            }
        } catch (Exception $e) {
            error_log("Could not show keyboard shortcuts help: " . $e->getMessage());
        }
    }

    /**
     * Show about dialog
     * 
     * @return void
     */
    private function showAboutDialog(): void
    {
        try {
            $aboutText = "OHA GUI Tool\n\n" .
                        "A cross-platform GUI for HTTP load testing using oha.\n\n" .
                        "Features:\n" .
                        "• Easy configuration of HTTP load tests\n" .
                        "• Save and load test configurations\n" .
                        "• Real-time test execution and results\n" .
                        "• Cross-platform support (Windows, macOS, Linux)\n\n" .
                        "Built with PHP and libui.";
            
            if (class_exists('Kingbes\Libui\Window\MsgBox')) {
                MsgBox::info($this->window, 'About OHA GUI Tool', $aboutText);
            } else {
                // Fallback: display in results area
                if ($this->resultsDisplay) {
                    $this->resultsDisplay->appendOutput("\n" . $aboutText . "\n");
                }
            }
        } catch (Exception $e) {
            error_log("Could not show about dialog: " . $e->getMessage());
        }
    }

    /**
     * Show help dialog with keyboard shortcuts and usage tips
     *
     * @return void
     */
    public function showHelpDialog(): void
    {
        $helpText = UserGuidance::getKeyboardShortcutsHelp() . "\n";
        $helpText .= UserGuidance::getUsageTips();

        try {
            if (class_exists('Kingbes\Libui\Window\MsgBox')) {
                MsgBox::info($this->window, 'Help - OHA GUI Tool', $helpText);
            } else {
                $this->resultsDisplay->appendOutput($helpText . "\n");
            }
        } catch (Exception $e) {
            $this->resultsDisplay->appendOutput($helpText . "\n");
        }
    }

    /**
     * Show the configuration manager window
     *
     * @return void
     */
    private function showConfigurationManager(): void
    {
        if ($this->configManagerWindow) {
            $this->configManagerWindow->show();
        }
    }



    /**
     * Get available keyboard shortcuts
     * 
     * @return array
     */
    public function getKeyboardShortcuts(): array
    {
        return $this->keyboardShortcuts;
    }

    /**
     * Add performance interpretation to help users understand results
     * 
     * @param mixed $result
     * @return void
     */
    private function addPerformanceInterpretation($result): void
    {
        $this->resultsDisplay->appendOutput("💡 PERFORMANCE ANALYSIS:\n");
        
        // Interpret requests per second
        if ($result->requestsPerSecond < 10) {
            $this->resultsDisplay->appendOutput("• Low throughput - Consider checking server performance or network latency\n");
        } elseif ($result->requestsPerSecond < 100) {
            $this->resultsDisplay->appendOutput("• Moderate throughput - Typical for many web applications\n");
        } elseif ($result->requestsPerSecond < 1000) {
            $this->resultsDisplay->appendOutput("• Good throughput - Server is handling load well\n");
        } else {
            $this->resultsDisplay->appendOutput("• Excellent throughput - High-performance server\n");
        }
        
        // Interpret success rate
        if ($result->successRate < 95) {
            $this->resultsDisplay->appendOutput("• ⚠️  Low success rate - Server may be overloaded or experiencing issues\n");
        } elseif ($result->successRate < 99) {
            $this->resultsDisplay->appendOutput("• Acceptable success rate - Some requests failed, monitor server health\n");
        } else {
            $this->resultsDisplay->appendOutput("• Excellent success rate - Server handling requests reliably\n");
        }
        
        $this->resultsDisplay->appendOutput("\n");
    }

    /**
     * Provide guidance based on exit code
     * 
     * @param int $exitCode
     * @return void
     */
    private function provideExitCodeGuidance(int $exitCode): void
    {
        $this->resultsDisplay->appendOutput("\n💡 TROUBLESHOOTING:\n");
        
        switch ($exitCode) {
            case 1:
                $this->resultsDisplay->appendOutput("• General error - Check URL accessibility and network connection\n");
                break;
            case 2:
                $this->resultsDisplay->appendOutput("• Invalid arguments - Verify test configuration parameters\n");
                break;
            case 130:
                $this->resultsDisplay->appendOutput("• Test interrupted - This is normal if you stopped the test manually\n");
                break;
            default:
                $this->resultsDisplay->appendOutput("• Unexpected exit code - Check oha documentation or try with simpler parameters\n");
                break;
        }
        
        $this->resultsDisplay->appendOutput("• Try reducing concurrent connections or test duration\n");
        $this->resultsDisplay->appendOutput("• Verify the target URL is accessible in a web browser\n");
        $this->resultsDisplay->appendOutput("• Check firewall and network settings\n\n");
    }
}