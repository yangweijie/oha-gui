<?php

/**
 * OHA GUI Tool - Visual Main Interface Prototype
 *
 * This is a prototype implementation of the main interface shown in Image #1
 * using nested Box and Group layouts to create a visual design.
 */

declare(strict_types=1);

// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define application constants
define('APP_NAME', 'OHA GUI Tool');
define('APP_VERSION', '1.0.0');
define('APP_ROOT', __DIR__);

// Load composer autoloader
require_once APP_ROOT . '/vendor/autoload.php';

// Import required classes
use Kingbes\Libui\App;
use Kingbes\Libui\Window;
use Kingbes\Libui\Control;
use Kingbes\Libui\Box;
use Kingbes\Libui\Group;
use Kingbes\Libui\Label;
use Kingbes\Libui\Button;
use Kingbes\Libui\Entry;
use Kingbes\Libui\Table;
use Kingbes\Libui\TableValueType;
use Kingbes\Libui\EditableCombobox;
use Kingbes\Libui\Separator;
use Kingbes\Libui\ProgressBar;
use OhaGui\Core\ConfigurationManager;
use OhaGui\Core\TestExecutor;
use OhaGui\Core\OhaCommandBuilder;
use OhaGui\Core\ResultParser;
use OhaGui\Models\TestConfiguration;
use OhaGui\Utils\UserGuidance;
use OhaGui\Utils\CrossPlatform;

try {
    // Initialize libui
    App::init();

    // Create main window
    $window = Window::create('OHA GUI Tool', 600, 400, 0);
    Window::setMargined($window, true);

    // Handle window closing
    Window::onClosing($window, function($window) {
        App::quit();
        return 1;
    });

    // Create main vertical box for layout (as shown in Image #1)
    $mainBox = Box::newVerticalBox();
    Box::setPadded($mainBox, true);

    // First horizontal box - contains input group and results group
    $topHorizontalBox = Box::newHorizontalBox();
    Box::setPadded($topHorizontalBox, true);

    // Input group (left side)
    $inputGroup = Group::create('输入');
    $inputBox = Box::newVerticalBox();
    Box::setPadded($inputBox, true);

    // Configuration label and combobox in horizontal box
    $configRow = Box::newHorizontalBox();
    Box::setPadded($configRow, true);

    $configLabel = Label::create('配置');
    Box::append($configRow, $configLabel, false);

    $configCombo = EditableCombobox::create();

    // Load configuration names from the config directory
    $configManager = new ConfigurationManager();
    $configurations = $configManager->listConfigurations();

    // Add configuration names to the combobox
    foreach ($configurations as $config) {
        EditableCombobox::append($configCombo, $config['name']);
    }

    EditableCombobox::setText($configCombo, 'Select Configuration...');
    Box::append($configRow, $configCombo, true);

    $manageButton = Button::create('管理');
    Box::append($configRow, $manageButton, false);

    Box::append($inputBox, $configRow, false);

    // Buttons horizontal box
    $buttonsBox = Box::newHorizontalBox();
    Box::setPadded($buttonsBox, true);

    $startButton = Button::create('开始');
    Box::append($buttonsBox, $startButton, true);

    $stopButton = Button::create('停止');
    Control::disable($stopButton); // Initially disabled
    Box::append($buttonsBox, $stopButton, true);

    Box::append($inputBox, $buttonsBox, false);

    // Progress bar (initially hidden)
    $progressBar = ProgressBar::create();
    ProgressBar::setValue($progressBar, 0);
    Control::hide($progressBar);
    Box::append($inputBox, $progressBar, false);

    // Add event handler for manage button
    Button::onClicked($manageButton, function() use ($window, $configCombo) {
        // Create a new window for configuration management
        $configWindow = Window::create('Configuration Management', 800, 600, 0);
        Window::setMargined($configWindow, true);

        // Handle window closing
        Window::onClosing($configWindow, function($configWindow) {
            Control::hide($configWindow);
            return 1;
        });

        // Create main vertical box for layout
        $configMainBox = Box::newVerticalBox();
        Box::setPadded($configMainBox, true);

        // Form section
        $formBox = Box::newVerticalBox();
        Box::setPadded($formBox, true);

        // Fields for configuration
        $fields = ['Name', 'URL', 'Concurrent Connections', 'Duration', 'Timeout'];
        $entries = [];
        foreach ($fields as $field) {
            $label = Label::create($field);
            $entry = Entry::create();
            Box::append($formBox, $label, false);
            Box::append($formBox, $entry, false);
            $entries[$field] = $entry;
        }

        // Save button
        $saveBtn = Button::create('Save Configuration');
        Box::append($formBox, $saveBtn, false);
        Box::append($formBox, Separator::createHorizontal(), false);

        // Search section
        $searchEntry = Entry::create();
        Entry::setText($searchEntry, '');
        $searchBtn = Button::create('Search');
        $searchBox = Box::newHorizontalBox();
        Box::setPadded($searchBox, true);
        Box::append($searchBox, $searchEntry, true);
        Box::append($searchBox, $searchBtn, false);
        Box::append($formBox, $searchBox, false);
        Box::append($formBox, Separator::createHorizontal(), false);

        // Initialize configuration manager
        $configManager = new ConfigurationManager();

        // Get configurations from file
        $configurations = $configManager->listConfigurations();
        $filteredConfigurations = $configurations;

        // Selected configuration callback
        $selectedConfigCallback = null;

        // Function to refresh the main configuration combobox
        $refreshMainConfigCombo = function() use ($configCombo, $configManager) {
            // Get current text before clearing
            $currentText = EditableCombobox::text($configCombo);
            
            // Get configurations from manager
            $configurations = $configManager->listConfigurations();
            
            // Since we can't clear the combobox items directly, we'll add any new configurations
            // that aren't already there. The EditableCombobox will handle duplicates.
            foreach ($configurations as $config) {
                EditableCombobox::append($configCombo, $config['name']);
            }
            
            // Check if the current configuration still exists
            $configExists = false;
            foreach ($configurations as $config) {
                if ($config['name'] === $currentText) {
                    $configExists = true;
                    break;
                }
            }
            
            // If the current configuration doesn't exist anymore, set to default text
            if (!$configExists && !empty($currentText) && $currentText !== 'Select Configuration...') {
                EditableCombobox::setText($configCombo, 'Select Configuration...');
            }
        };

        // Function to create a confirmation dialog
        $createConfirmDialog = function($parentWindow, $title, $message, $onConfirm, $onCancel = null) use (&$filteredConfigurations, $configManager) {
            // Create a new window for the confirmation dialog
            $dialogWindow = Window::create($title, 300, 150, 1); // 1 = modal window
            Window::setMargined($dialogWindow, true);
            
            // Create main vertical box for layout
            $dialogBox = Box::newVerticalBox();
            Box::setPadded($dialogBox, true);
            
            // Add message label
            $messageLabel = Label::create($message);
            Box::append($dialogBox, $messageLabel, false);
            
            // Add buttons box
            $buttonsBox = Box::newHorizontalBox();
            Box::setPadded($buttonsBox, true);
            
            // Confirm button
            $confirmButton = Button::create('Confirm');
            Button::onClicked($confirmButton, function() use ($dialogWindow, $onConfirm) {
                Control::hide($dialogWindow);
                if (is_callable($onConfirm)) {
                    $onConfirm();
                }
            });
            Box::append($buttonsBox, $confirmButton, true);
            
            // Cancel button
            $cancelButton = Button::create('Cancel');
            Button::onClicked($cancelButton, function() use ($dialogWindow, $onCancel) {
                Control::hide($dialogWindow);
                if (is_callable($onCancel)) {
                    $onCancel();
                }
            });
            Box::append($buttonsBox, $cancelButton, true);
            
            Box::append($dialogBox, $buttonsBox, false);
            
            Window::setChild($dialogWindow, $dialogBox);
            Control::show($dialogWindow);
        };

        // Table model handler
        $getTableModelHandler = function() use (&$filteredConfigurations, &$selectedConfigCallback, $configWindow, $configCombo, $configManager, $createConfirmDialog, $refreshMainConfigCombo, $formBox, &$table, &$createTable, &$configurations) {
            return Table::modelHandler(
                7, // 7 columns: Name, URL, Concurrent Connections, Duration, Timeout, Select, Delete
                TableValueType::String,
                count($filteredConfigurations),
                function ($handler, $row, $column) use (&$filteredConfigurations) {
                    if ($row >= count($filteredConfigurations)) {
                        return Table::createValueStr('');
                    }

                    // Get the configuration data for this row
                    $configKeys = array_keys($filteredConfigurations);
                    if ($row >= count($configKeys)) {
                        return Table::createValueStr('');
                    }

                    $configKey = $configKeys[$row];
                    $configData = $filteredConfigurations[$configKey];

                    switch ($column) {
                        case 0:
                            return Table::createValueStr($configData['name'] ?? '');
                        case 1:
                            return Table::createValueStr($configData['url'] ?? '');
                        case 2:
                            return Table::createValueStr((string)($configData['concurrentConnections'] ?? '0'));
                        case 3:
                            return Table::createValueStr((string)($configData['duration'] ?? '0'));
                        case 4:
                            return Table::createValueStr((string)($configData['timeout'] ?? '0'));
                        case 5:
                            return Table::createValueStr('Select');
                        case 6:
                            return Table::createValueStr('Delete');
                        default:
                            return Table::createValueStr('');
                    }
                },
                function ($handler, $row, $column, $v) use (&$filteredConfigurations, $configWindow, $configCombo, $configManager, $createConfirmDialog, $refreshMainConfigCombo, $formBox, &$table, &$createTable, &$configurations, &$tableModelRef) {
                    // Get the configuration name from the table data
                    $configKeys = array_keys($filteredConfigurations);
                    if ($row >= count($configKeys)) {
                        return;
                    }
                    
                    $configKey = $configKeys[$row];
                    $configData = $filteredConfigurations[$configKey];
                    $name = $configData['name'];

                    // Handle button click in the Select column (column 5)
                    if ($column == 5 && $row < count($filteredConfigurations)) {
                        // Load the configuration
                        $config = $configManager->loadConfiguration($name);
                        if ($config) {
                            // Update the main window combobox
                            EditableCombobox::setText($configCombo, $name);

                            // Hide the configuration window
                            Control::hide($configWindow);
                        }
                    }
                    // Handle button click in the Delete column (column 6)
                    else if ($column == 6 && $row < count($filteredConfigurations)) {
                        // Create a confirmation dialog
                        $createConfirmDialog(
                            $configWindow,
                            'Confirm Delete',
                            "Are you sure you want to delete the configuration '{$name}'? This action cannot be undone.",
                            function() use (&$filteredConfigurations, &$configurations, $name, $configManager, $configWindow, $configCombo, $refreshMainConfigCombo, $formBox, &$table, &$createTable, &$tableModelRef) {
                                // Find the row index of the configuration to delete
                                $rowIndex = -1;
                                foreach ($filteredConfigurations as $index => $config) {
                                    if ($config['name'] === $name) {
                                        $rowIndex = $index;
                                        break;
                                    }
                                }
                                
                                // Delete the configuration
                                if ($rowIndex >= 0 && $configManager->deleteConfiguration($name)) {
                                    // Refresh configurations list
                                    $configurations = $configManager->listConfigurations();
                                    $filteredConfigurations = $configurations;
                                    
                                    // Also update the main window combobox
                                    $refreshMainConfigCombo();
                                    
                                    // Update the table view by deleting the row
                                    if ($tableModelRef !== null && $rowIndex >= 0) {
                                        // Call modelRowDeleted to update the table view
                                        Table::modelRowDeleted($tableModelRef, $rowIndex);
                                    }
                                }
                            }
                        );
                    }
                }
            );
        };

        // Variable to hold the table model reference
        $tableModelRef = null;

        // Function to create and setup the table
        $createTable = function() use ($getTableModelHandler, $formBox, &$tableModelRef) {
            // Create table model and table
            $tableModelRef = Table::createModel($getTableModelHandler());
            $table = Table::create($tableModelRef, -1);
            Table::appendTextColumn($table, 'Name', 0, false);
            Table::appendTextColumn($table, 'URL', 1, false);
            Table::appendTextColumn($table, 'Concurrent Connections', 2, false);
            Table::appendTextColumn($table, 'Duration', 3, false);
            Table::appendTextColumn($table, 'Timeout', 4, false);
            Table::appendButtonColumn($table, 'Action', 5, true);
            Table::appendButtonColumn($table, 'Delete', 6, true);
            Box::append($formBox, $table, true);
            return $table;
        };

        // Create the initial table
        $table = $createTable();

        // Function to refresh the main configuration combobox
        $refreshMainConfigCombo = function() use ($configCombo, $configManager) {
            // Clear existing items
            // Note: libui doesn't provide a direct way to clear items, so we'll need to recreate
            // For now, we'll just add any new configurations that aren't already there
            $currentText = EditableCombobox::text($configCombo);
            $configurations = $configManager->listConfigurations();

            foreach ($configurations as $config) {
                // libui doesn't have a way to check if an item exists, so we'll just add all
                // The EditableCombobox will handle duplicates
                EditableCombobox::append($configCombo, $config['name']);
            }
        };

        // Save button event handler
        Button::onClicked($saveBtn, function () use (&$configurations, &$filteredConfigurations, $entries, $configWindow, $table, $getTableModelHandler, $tableModelRef, $configManager, $refreshMainConfigCombo, $formBox, $configCombo) {
            $row = [];
            $allFilled = true;
            foreach (['Name', 'URL', 'Concurrent Connections', 'Duration', 'Timeout'] as $field) {
                $val = Entry::text($entries[$field]);
                if (trim($val) === '') {
                    $allFilled = false;
                }
                $row[$field] = $val;
            }
            if ($allFilled) {
                // Create a TestConfiguration object
                $testConfig = new TestConfiguration(
                    $row['Name'],
                    $row['URL'],
                    'GET', // Default method
                    (int)$row['Concurrent Connections'],
                    (int)$row['Duration'],
                    (int)$row['Timeout'],
                    [], // Default headers
                    ''  // Default body
                );

                // Save configuration using the configuration manager
                $success = $configManager->saveConfiguration($row['Name'], $testConfig);

                if ($success) {
                    // Clear form
                    foreach ($entries as $entry) {
                        Entry::setText($entry, '');
                    }

                    // Refresh configurations list
                    $configurations = $configManager->listConfigurations();
                    $filteredConfigurations = $configurations;

                    // Update table with new data
                    // Since there's no setModel method, we need to recreate the entire table
                    // First, remove the old table from the form box
                    // Note: This is a limitation of the libui PHP binding

                    // Also update the main window combobox
                    EditableCombobox::setText($configCombo, $row['Name']);

                    // Refresh the main configuration combobox
                    $refreshMainConfigCombo();
                }
            }
        });

        // Search button event handler
        Button::onClicked($searchBtn, function () use (&$configurations, &$filteredConfigurations, $searchEntry, $table, $getTableModelHandler, $configManager, $formBox) {
            $keyword = trim(Entry::text($searchEntry));
            $allConfigurations = $configManager->listConfigurations();

            if ($keyword === '') {
                $filteredConfigurations = $allConfigurations;
            } else {
                $filteredConfigurations = [];
                foreach ($allConfigurations as $config) {
                    $found = false;
                    foreach ($config as $key => $value) {
                        if (is_string($value) && strpos($value, $keyword) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if ($found) {
                        $filteredConfigurations[] = $config;
                    }
                }
            }

            // Update table with filtered data
            // Since there's no setModel method, we need to recreate the entire table
            // Note: This is a limitation of the libui PHP binding
        });

        Window::setChild($configWindow, $formBox);
        Control::show($configWindow);
    });

    Group::setChild($inputGroup, $inputBox);
    Box::append($topHorizontalBox, $inputGroup, false); // Don't expand input group

    // Results group (right side)
    $resultsGroup = Group::create('结果');
    $resultsBox = Box::newVerticalBox();
    Box::setPadded($resultsBox, true);

    // Results text
    $resultsText = Label::create("Ready to run test\nRequests/sec: --\nTotal requests: --\nSuccess rate: --\nPerformance: --");
    Box::append($resultsBox, $resultsText, false);

    Group::setChild($resultsGroup, $resultsBox);

    Box::append($topHorizontalBox, $resultsGroup, true); // Expand results group

    Box::append($mainBox, $topHorizontalBox, false);

    // Second horizontal box - contains title and multiline text box
    $bottomHorizontalBox = Box::newHorizontalBox();
    Box::setPadded($bottomHorizontalBox, true);

    // Output group
    $outputGroup = Group::create('测试输出');
    $outputBox = Box::newVerticalBox();
    Box::setPadded($outputBox, true);

    // Add output label with placeholder text
    $outputLabel = Label::create('Test output will appear here...');
    Box::append($outputBox, $outputLabel, false);

    Group::setChild($outputGroup, $outputBox);
    Box::append($bottomHorizontalBox, $outputGroup, true);

    Box::append($mainBox, $bottomHorizontalBox, true);

    // Create test executor and command builder instances
    $testExecutor = new TestExecutor();
    $testExecutor->setMaxOutputSize(102400); // Limit output to 100KB to prevent memory issues
    $testExecutor->setMinReadInterval(50); // Set minimum read interval to 50ms
    $commandBuilder = new OhaCommandBuilder();
    $resultParser = new ResultParser();

    // Add event handlers for start and stop buttons
    Button::onClicked($startButton, function() use ($startButton, $stopButton, $progressBar, $configCombo, $resultsText, $outputLabel, $testExecutor, $commandBuilder, $resultParser) {
        try {
            // Get selected configuration name
            $configName = trim(EditableCombobox::text($configCombo));

            if (empty($configName) || $configName === 'Select Configuration...') {
                Label::setText($outputLabel, "Please select a configuration first.");
                return;
            }

            // Load configuration
            $configManager = new ConfigurationManager();
            $config = $configManager->loadConfiguration($configName);

            if (!$config) {
                Label::setText($outputLabel, "Failed to load configuration: {$configName}");
                return;
            }

            // Validate configuration
            $errors = $config->validate();
            if (!empty($errors)) {
                $guidance = UserGuidance::getErrorGuidance('validation_failed', implode(', ', $errors));
                Label::setText($outputLabel, "Configuration Error: " . $guidance['message']);
                return;
            }

            // Check if oha binary is available
            if (!$commandBuilder->isOhaAvailable()) {
                $guidance = UserGuidance::getErrorGuidance('oha_not_found');
                Label::setText($outputLabel, "Error: " . $guidance['message']);
                return;
            }

            // Build command
            $command = $commandBuilder->buildCommand($config);
            
            // Disable start button and enable stop button
            Control::disable($startButton);
            Control::enable($stopButton);

            // Show progress bar and set to indeterminate mode
            Control::show($progressBar);
            ProgressBar::setValue($progressBar, -1); // Indeterminate progress

            // Update UI to show test is starting
            Label::setText($resultsText, "Test starting...\nRequests/sec: --\nTotal requests: --\nSuccess rate: --\nPerformance: --");
            $currentOutput = Label::text($outputLabel);
            Label::setText($outputLabel, $currentOutput . "Starting test with configuration: {$configName}\nURL: {$config->url}\nConnections: {$config->concurrentConnections}\nDuration: {$config->duration}s\nTimeout: {$config->timeout}s\nCommand: " . $command . "\n\n");

            // Start test execution with proper callbacks
            $testExecutor->executeTest(
                $command,
                function($output) use ($outputLabel) {
                    // Real-time output callback - use queueMain for thread-safe GUI updates
                    App::queueMain(function() use ($outputLabel, $output) {
                        $currentText = Label::text($outputLabel);
                        // Ensure proper line endings
                        $formattedOutput = str_replace("
", "
", $output);
                        Label::setText($outputLabel, $currentText . $formattedOutput);
                    });
                },
                function($error) use ($outputLabel, $startButton, $stopButton, $progressBar) {
                    // Error callback - use queueMain for thread-safe GUI updates
                    App::queueMain(function() use ($outputLabel, $startButton, $stopButton, $progressBar, $error) {
                        $errorMessage = "Test Error: " . ($error['message'] ?? 'Unknown error');
                        Label::setText($outputLabel, Label::text($outputLabel) . "\n" . $errorMessage . "\n");
                        
                        // Re-enable start button and disable stop button
                        Control::enable($startButton);
                        Control::disable($stopButton);
                        Control::hide($progressBar);
                        ProgressBar::setValue($progressBar, 0);
                    });
                },
                function($exitCode, $error = null) use ($outputLabel, $startButton, $stopButton, $progressBar, $resultsText, $resultParser, $testExecutor) {
                    // Completion callback - use queueMain for thread-safe GUI updates
                    App::queueMain(function() use ($outputLabel, $startButton, $stopButton, $progressBar, $resultsText, $resultParser, $testExecutor, $exitCode, $error) {
                        // Completion callback
                        Control::hide($progressBar);
                        ProgressBar::setValue($progressBar, 100);
                        
                        // Re-enable start button and disable stop button
                        Control::enable($startButton);
                        Control::disable($stopButton);
                        
                        if ($exitCode === 0) {
                            // Test completed successfully
                            $output = $testExecutor->getOutput();
                            // Ensure proper line endings
                            $formattedOutput = str_replace("
", "
", $output);
                            Label::setText($outputLabel, Label::text($outputLabel) . "
Test completed successfully.
" . $formattedOutput);
                            
                            // Try to parse results
                            try {
                                $result = $resultParser->parseOutput($output);
                                $results = "Test completed
";
                                $results .= "Requests/sec: " . ($result->requestsPerSecond ?? '--') . "
";
                                $results .= "Total requests: " . ($result->totalRequests ?? '--') . "
";
                                $results .= "Success rate: " . ($result->successRate ?? '--') . "%
";
                                $results .= "Performance: " . ($result->performance ?? '--') . "
";
                                Label::setText($resultsText, $results);
                            } catch (Exception $e) {
                                Label::setText($resultsText, "Test completed
Failed to parse results.
");
                                // Even if parsing fails, show the raw output
                                $formattedOutput = str_replace("
", "
", $output);
                                Label::setText($outputLabel, Label::text($outputLabel) . "
Raw output:
" . $formattedOutput);
                            }
                        } else {
                            // Test failed
                            $output = $testExecutor->getOutput();
                            // Ensure proper line endings
                            $formattedOutput = str_replace("
", "
", $output);
                            Label::setText($outputLabel, Label::text($outputLabel) . "
Test failed with exit code: {$exitCode}
" . $formattedOutput);
                            Label::setText($resultsText, "Test failed
Requests/sec: --
Total requests: --
Success rate: --
Performance: --");
                        }
                    });
                }
            );
            
            // Start monitoring test progress by periodically checking if the test is still running
            // Use a simple approach without recursive calls to prevent memory leaks
            $monitorTestProgress = function() use ($testExecutor, $progressBar) {
                // This function will be called periodically by the GUI event loop
                // We don't need to recursively call it ourselves
            };
            
            // Start the monitoring
            $monitorTestProgress();
        } catch (Exception $e) {
            // Handle errors gracefully in FFI callbacks
            error_log("Error in start button callback: " . $e->getMessage());
            Label::setText($outputLabel, "Error starting test: " . $e->getMessage());
            Control::enable($startButton);
            Control::disable($stopButton);
            Control::hide($progressBar);
        }
    });

    Button::onClicked($stopButton, function() use ($startButton, $stopButton, $progressBar, $resultsText, $outputLabel, $testExecutor) {
        try {
            // Stop the test if it's running
            if ($testExecutor->isRunning()) {
                $testExecutor->stopTest();
            }
            
            // Enable start button and disable stop button
            Control::enable($startButton);
            Control::disable($stopButton);

            // Hide progress bar
            Control::hide($progressBar);
            ProgressBar::setValue($progressBar, 0);

            // Update UI to show test was stopped
            Label::setText($resultsText, "Test stopped\nRequests/sec: --\nTotal requests: --\nSuccess rate: --\nPerformance: --");
            Label::setText($outputLabel, Label::text($outputLabel) . "\nTest stopped by user.\n");
        } catch (Exception $e) {
            // Handle errors gracefully in FFI callbacks
            error_log("Error in stop button callback: " . $e->getMessage());
            Label::setText($outputLabel, Label::text($outputLabel) . "\nError stopping test: " . $e->getMessage());
        }
    });

    // Add event handler for manage button

    // Set window content
    Window::setChild($window, $mainBox);

    // Show window
    Control::show($window);

    // Run the main event loop
    App::main();

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    exit(1);
}