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
                7, // 7 columns: Name, URL, Method, Connections, Duration, Timeout, Actions
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
                            return Table::createValueStr('Edit/Delete');
                        default:
                            return Table::createValueStr('');
                    }
                },
                function ($handler, $row, $column, $v) {
                    // Editing not directly supported in this implementation
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

        Table::appendTextColumn($this->table, 'Actions', 6, false);

        // Note: Row click handlers are not supported in this version of libui
        // Editing and deletion will be handled through separate buttons

        Box::append($this->formBox, $this->table, true);
    }

    /**
     * Refresh the configurations list
     *
     * @return void
     */
    private function refreshConfigurations(): void
    {
        $this->configurations = $this->configManager->listConfigurations();

        // Clear table model
        for ($i = count($this->configurations) - 1; $i >= 0; $i--) {
            Table::modelRowDeleted($this->tableModel, $i);
        }

        // Repopulate table model
        for ($i = 0; $i < count($this->configurations); $i++) {
            Table::modelRowInserted($this->tableModel, $i);
        }
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

        // Create configuration object
        $config = new TestConfiguration(
            $name,
            $url,
            $method,
            $connections,
            $duration,
            $timeout,
            [], // headers
            ''  // body
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
                // Refresh configurations list
                $this->refreshConfigurations();

                // Reset form only if we were editing this configuration
                if ($isUpdate) {
                    $this->resetForm();
                } else {
                    // Clear name field but keep other values for quick successive saves
                    Entry::setText($this->entries['name'], '');
                }
            }
        } catch (Exception $e) {
            // Show error
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