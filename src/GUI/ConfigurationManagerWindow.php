<?php

namespace OhaGui\GUI;

use Kingbes\Libui\App;
use Kingbes\Libui\Window;
use Kingbes\Libui\Control;
use Kingbes\Libui\Table;
use Kingbes\Libui\TableValueType;
use Kingbes\Libui\Label;
use Kingbes\Libui\Entry;
use Kingbes\Libui\Button;
use Kingbes\Libui\Separator;
use Kingbes\Libui\Box;
use FFI\CData;
use OhaGui\Models\TestConfiguration;
use OhaGui\Core\ConfigurationManager;
use Exception;

/**
 * Configuration Manager Window
 *
 * A popup window for managing test configurations with a form-table interface
 */
class ConfigurationManagerWindow
{
    private CData $window;
    private CData $formBox;
    private array $entries = [];
    private CData $table;
    private CData $tableModel;
    private ConfigurationManager $configManager;
    private array $configurations = [];
    private ?string $editingConfigName = null;
    private CData $saveButton;
    private CData $resetButton;

    private $onCloseCallback = null;

    /**
     * Initialize the configuration manager window
     *
     * @param CData|null $parentWindow Parent window for positioning
     */
    public function __construct(?CData $parentWindow = null)
    {
        $this->configManager = new ConfigurationManager();
        $this->createWindow();
        $this->createForm();
        $this->createTable();
        $this->refreshConfigurations();
    }

    /**
     * Create the main window
     *
     * @return void
     */
    private function createWindow(): void
    {
        $this->window = Window::create('Configuration Manager', 800, 600, 0);
        Window::setMargined($this->window, true);

        Window::onClosing($this->window, function ($window) {
            return $this->onWindowClosing();
        });
    }

    /**
     * Create the form section
     *
     * @return void
     */
    private function createForm(): void
    {
        $this->formBox = Box::newVerticalBox();
        Box::setPadded($this->formBox, true);

        // Name field
        $nameLabel = Label::create('Name:');
        $this->entries['name'] = Entry::create();
        Box::append($this->formBox, $nameLabel, false);
        Box::append($this->formBox, $this->entries['name'], false);

        // URL field
        $urlLabel = Label::create('URL:');
        $this->entries['url'] = Entry::create();
        Entry::setText($this->entries['url'], 'http://localhost:8080');
        Box::append($this->formBox, $urlLabel, false);
        Box::append($this->formBox, $this->entries['url'], false);

        // Method field
        $methodLabel = Label::create('Method:');
        $this->entries['method'] = Entry::create();
        Entry::setText($this->entries['method'], 'GET');
        Box::append($this->formBox, $methodLabel, false);
        Box::append($this->formBox, $this->entries['method'], false);

        // Connections field
        $connectionsLabel = Label::create('Connections:');
        $this->entries['connections'] = Entry::create();
        Entry::setText($this->entries['connections'], '10');
        Box::append($this->formBox, $connectionsLabel, false);
        Box::append($this->formBox, $this->entries['connections'], false);

        // Duration field
        $durationLabel = Label::create('Duration (seconds):');
        $this->entries['duration'] = Entry::create();
        Entry::setText($this->entries['duration'], '10');
        Box::append($this->formBox, $durationLabel, false);
        Box::append($this->formBox, $this->entries['duration'], false);

        // Timeout field
        $timeoutLabel = Label::create('Timeout (seconds):');
        $this->entries['timeout'] = Entry::create();
        Entry::setText($this->entries['timeout'], '30');
        Box::append($this->formBox, $timeoutLabel, false);
        Box::append($this->formBox, $this->entries['timeout'], false);

        // Headers field (multiline)
        $headersLabel = Label::create('Headers:');
        $this->entries['headers'] = \Kingbes\Libui\MultilineEntry::create();
        \Kingbes\Libui\MultilineEntry::setText($this->entries['headers'], "Content-Type: application/json\nUser-Agent: OHA-GUI-Tool");
        Box::append($this->formBox, $headersLabel, false);
        Box::append($this->formBox, $this->entries['headers'], false);

        // Body field (multiline)
        $bodyLabel = Label::create('Request Body:');
        $this->entries['body'] = \Kingbes\Libui\MultilineEntry::create();
        \Kingbes\Libui\MultilineEntry::setText($this->entries['body'], '{"key": "value"}');
        Box::append($this->formBox, $bodyLabel, false);
        Box::append($this->formBox, $this->entries['body'], false);

        // Buttons
        $buttonBox = Box::newHorizontalBox();
        Box::setPadded($buttonBox, true);

        $this->saveButton = Button::create('Add');
        $this->resetButton = Button::create('Reset');

        Box::append($buttonBox, $this->saveButton, false);
        Box::append($buttonBox, $this->resetButton, false);

        Box::append($this->formBox, $buttonBox, false);
        Box::append($this->formBox, Separator::createHorizontal(), false);

        // Event handlers
        Button::onClicked($this->saveButton, function () {
            $this->onSaveConfiguration();
        });

        Button::onClicked($this->resetButton, function () {
            $this->resetForm();
        });

        // Name change handler to toggle between Add/Update
        Entry::onChanged($this->entries['name'], function ($entry) {
            $this->onNameChanged();
        });
    }

    /**
     * Create the table section
     *
     * @return void
     */
    private function createTable(): void
    {
        // Table model handler
        $getTableModelHandler = function () {
            return Table::modelHandler(
                9, // 9 columns: Name, URL, Method, Connections, Duration, Timeout, Headers, Body, Actions
                TableValueType::String,
                count($this->configurations),
                function ($handler, $row, $column) {
                    if ($row >= count($this->configurations)) {
                        return Table::createValueStr('');
                    }

                    $config = array_values($this->configurations)[$row];
                    switch ($column) {
                        case 0:
                            return Table::createValueStr($config['name'] ?? '');
                        case 1:
                            return Table::createValueStr($config['url'] ?? '');
                        case 2:
                            return Table::createValueStr($config['method'] ?? '');
                        case 3:
                            return Table::createValueStr((string)($config['concurrentConnections'] ?? ''));
                        case 4:
                            return Table::createValueStr((string)($config['duration'] ?? ''));
                        case 5:
                            return Table::createValueStr((string)($config['timeout'] ?? ''));
                        case 6:
                            // Format headers for display
                            $headers = $config['headers'] ?? [];
                            $headerCount = count($headers);
                            return Table::createValueStr($headerCount > 0 ? "$headerCount headers" : "None");
                        case 7:
                            // Format body for display
                            $body = $config['body'] ?? '';
                            $bodyLength = strlen($body);
                            return Table::createValueStr($bodyLength > 0 ? "$bodyLength chars" : "Empty");
                        case 8:
                            return Table::createValueStr('Select');
                        default:
                            return Table::createValueStr('');
                    }
                },
                function ($handler, $row, $column, $v) {
                    // Handle button click in the last column
                    if ($column == 8 && $row < count($this->configurations)) {
                        // Get the configuration name from the table data
                        $configKeys = array_keys($this->configurations);
                        if ($row < count($configKeys)) {
                            $configKey = $configKeys[$row];
                            $configData = $this->configurations[$configKey];
                            $name = $configData['name'];
                            
                            // Load the configuration
                            $config = $this->configManager->loadConfiguration($name);
                            if ($config) {
                                // Update the main window combobox through callback
                                if ($this->onCloseCallback) {
                                    // Pass the selected configuration name to the callback
                                    ($this->onCloseCallback)($name, $config);
                                }
                                
                                // Hide the configuration window
                                $this->hide();
                            }
                        }
                    }
                }
            );
        };

        $this->tableModel = Table::createModel($getTableModelHandler());
        $this->table = Table::create($this->tableModel, -1);
        Table::appendTextColumn($this->table, 'Name', 0, false);
        Table::appendTextColumn($this->table, 'URL', 1, false);
        Table::appendTextColumn($this->table, 'Method', 2, false);
        Table::appendTextColumn($this->table, 'Connections', 3, false);
        Table::appendTextColumn($this->table, 'Duration', 4, false);
        Table::appendTextColumn($this->table, 'Timeout', 5, false);
        Table::appendTextColumn($this->table, 'Headers', 6, false);
        Table::appendTextColumn($this->table, 'Body', 7, false);

        Table::appendButtonColumn($this->table, 'Action', 8, false);

        // Note: Row click handlers are not supported in this version of libui
        // Editing and deletion will be handled through separate buttons

        Box::append($this->formBox, $this->table, true);
    }

    /**
     * Parse headers from multiline text
     *
     * @param string $headersText
     * @return array
     */
    private function parseHeaders(string $headersText): array
    {
        $headers = [];
        $lines = explode("\n", $headersText);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                if (!empty($key)) {
                    $headers[$key] = $value;
                }
            }
        }

        return $headers;
    }

    /**
     * Format headers array to multiline text
     *
     * @param array $headers
     * @return string
     */
    private function formatHeaders(array $headers): string
    {
        $lines = [];
        foreach ($headers as $key => $value) {
            $lines[] = "$key: $value";
        }
        return implode("\n", $lines);
    }

    /**
     * Refresh the configurations list
     *
     * @return void
     */
    private function refreshConfigurations(): void
    {
        $this->configurations = $this->configManager->listConfigurations();
        
        // Debug: Log the number of configurations
        error_log("Refreshing configurations. Count: " . count($this->configurations));

        // Note: We can't directly get the number of rows in the table model
        // In a real implementation, we would need to track this ourselves
        // For now, we'll just repopulate the table model
        // This may cause issues if the number of configurations changes
        // A better approach would be to recreate the entire table
    }

    /**
     * Show the window
     *
     * @return void
     */
    public function show(): void
    {
        Control::show($this->window);
    }

    /**
     * Hide the window
     *
     * @return void
     */
    public function hide(): void
    {
        Control::hide($this->window);
    }

    /**
     * Handle window closing
     *
     * @return int
     */
    private function onWindowClosing(): int
    {
        $this->hide();

        if ($this->onCloseCallback) {
            // Call the callback without parameters when closing the window normally
            ($this->onCloseCallback)();
        }

        return 0; // Prevent actual window destruction to allow reuse
    }

    /**
     * Set callback for window close event
     *
     * @param callable $callback
     * @return void
     */
    public function setOnCloseCallback(callable $callback): void
    {
        $this->onCloseCallback = $callback;
    }

    /**
     * Handle save configuration
     *
     * @return void
     */
    private function onSaveConfiguration(): void
    {
        $name = trim(Entry::text($this->entries['name']));
        if (empty($name)) {
            // Show error - name is required
            return;
        }

        // Get values from form
        $url = Entry::text($this->entries['url']);
        $method = Entry::text($this->entries['method']);
        $connections = (int)Entry::text($this->entries['connections']);
        $duration = (int)Entry::text($this->entries['duration']);
        $timeout = (int)Entry::text($this->entries['timeout']);

        // Get headers and body
        $headersText = \Kingbes\Libui\MultilineEntry::text($this->entries['headers']);
        $headers = $this->parseHeaders($headersText);
        $body = \Kingbes\Libui\MultilineEntry::text($this->entries['body']);

        // Create configuration object
        $config = new TestConfiguration(
            $name,
            $url,
            $method,
            $connections,
            $duration,
            $timeout,
            $headers,
            $body
        );

        // Validate configuration
        $errors = $config->validate();
        if (!empty($errors)) {
            // Show validation errors
            return;
        }

        // Check if we're updating or creating
        $isUpdate = $this->editingConfigName !== null && $this->editingConfigName === $name;

        try {
            // Save configuration (will update if exists, create if not)
            $success = $this->configManager->saveConfiguration($name, $config);

            if ($success) {
                error_log("Configuration saved successfully: " . $name);
                // Refresh configurations list
                $this->refreshConfigurations();

                // Reset form only if we were editing this configuration
                if ($isUpdate) {
                    $this->resetForm();
                } else {
                    // Clear name field but keep other values for quick successive saves
                    Entry::setText($this->entries['name'], '');
                }
            } else {
                error_log("Failed to save configuration: " . $name);
                // Show error - failed to save
                // In a real implementation, you would show an error message to the user
            }
        } catch (Exception $e) {
            error_log("Exception while saving configuration: " . $e->getMessage());
            // Show error
            // In a real implementation, you would show an error message to the user
        }
    }

    /**
     * Reset the form
     *
     * @return void
     */
    private function resetForm(): void
    {
        foreach ($this->entries as $key => $entry) {
            if ($key === 'url') {
                Entry::setText($entry, 'http://localhost:8080');
            } elseif ($key === 'method') {
                Entry::setText($entry, 'GET');
            } elseif ($key === 'connections') {
                Entry::setText($entry, '10');
            } elseif ($key === 'duration') {
                Entry::setText($entry, '10');
            } elseif ($key === 'timeout') {
                Entry::setText($entry, '30');
            } elseif ($key === 'headers') {
                \Kingbes\Libui\MultilineEntry::setText($entry, "Content-Type: application/json\nUser-Agent: OHA-GUI-Tool");
            } elseif ($key === 'body') {
                \Kingbes\Libui\MultilineEntry::setText($entry, '{"key": "value"}');
            } else {
                Entry::setText($entry, '');
            }
        }

        $this->editingConfigName = null;
        Button::setText($this->saveButton, 'Add');
    }

    /**
     * Handle name field change
     *
     * @return void
     */
    private function onNameChanged(): void
    {
        $name = trim(Entry::text($this->entries['name']));
        if (!empty($name) && $this->configManager->configurationExists($name) && $name !== $this->editingConfigName) {
            Button::setText($this->saveButton, 'Update');
        } else if (!empty($name) && $name === $this->editingConfigName) {
            Button::setText($this->saveButton, 'Update');
        } else {
            Button::setText($this->saveButton, 'Add');
        }
    }

    /**
     * Edit a configuration
     *
     * @param int $row Row index in the table
     * @return void
     */
    private function editConfiguration(int $row): void
    {
        if ($row >= count($this->configurations)) {
            return;
        }

        // Get the configuration name from the table data
        $configKeys = array_keys($this->configurations);
        if ($row >= count($configKeys)) {
            return;
        }

        $configKey = $configKeys[$row];
        $configData = $this->configurations[$configKey];
        $name = $configData['name'];

        try {
            $config = $this->configManager->loadConfiguration($name);
            if ($config) {
                // Populate form with configuration data
                Entry::setText($this->entries['name'], $config->name);
                Entry::setText($this->entries['url'], $config->url);
                Entry::setText($this->entries['method'], $config->method);
                Entry::setText($this->entries['connections'], (string)$config->concurrentConnections);
                Entry::setText($this->entries['duration'], (string)$config->duration);
                Entry::setText($this->entries['timeout'], (string)$config->timeout);

                // Populate headers and body
                \Kingbes\Libui\MultilineEntry::setText($this->entries['headers'], $this->formatHeaders($config->headers));
                \Kingbes\Libui\MultilineEntry::setText($this->entries['body'], $config->body);

                // Set editing state
                $this->editingConfigName = $name;
                Button::setText($this->saveButton, 'Update');

                // Update button state
                $this->onNameChanged();
            }
        } catch (Exception $e) {
            // Show error
        }
    }

    /**
     * Delete a configuration by name
     *
     * @param string $name Configuration name
     * @return void
     */
    public function deleteConfiguration(string $name): void
    {
        try {
            $success = $this->configManager->deleteConfiguration($name);
            if ($success) {
                // Refresh configurations list
                $this->refreshConfigurations();

                // If we were editing this configuration, reset the form
                if ($this->editingConfigName === $name) {
                    $this->resetForm();
                }
            }
        } catch (Exception $e) {
            // Show error
        }
    }

    /**
     * Delete a configuration by row index
     *
     * @param int $row Row index in the table
     * @return void
     */
    private function deleteSelectedConfiguration(int $row): void
    {
        if ($row >= count($this->configurations)) {
            return;
        }

        // Get the configuration name from the table data
        $configKeys = array_keys($this->configurations);
        if ($row >= count($configKeys)) {
            return;
        }

        $configKey = $configKeys[$row];
        $configData = $this->configurations[$configKey];
        $name = $configData['name'];

        // Confirm deletion
        // In a full implementation, you would show a confirmation dialog
        // For now, we'll just proceed with deletion

        $this->deleteConfiguration($name);
    }

    /**
     * Add action buttons to table rows
     *
     * @return void
     */
    private function addTableActionButtons(): void
    {
        // This method would add edit/delete buttons to each row in the table
        // Since libui doesn't directly support buttons in table cells, we'll handle this through row clicks
        // The edit functionality is already implemented in the row click handler
    }

    /**
     * Get the window control
     *
     * @return CData
     */
    public function getWindow(): CData
    {
        return $this->window;
    }
}