<?php

namespace OhaGui\GUI;

use Kingbes\Libui\Form;
use Kingbes\Libui\Entry;
use Kingbes\Libui\Combobox;
use Kingbes\Libui\EditableCombobox;
use Kingbes\Libui\Spinbox;
use Kingbes\Libui\MultilineEntry;
use Kingbes\Libui\Button;
use Kingbes\Libui\Label;
use Kingbes\Libui\Box;
use Kingbes\Libui\Control;
use FFI\CData;
use OhaGui\Models\TestConfiguration;
use OhaGui\Core\InputValidator;
use OhaGui\Core\ConfigurationManager;
use Exception;

/**
 * Configuration Form component for HTTP load test parameters
 * 
 * Provides input fields for all oha test parameters with validation
 */
class ConfigurationForm
{
    private CData $form;
    private CData $saveConfigCombobox;
    private CData $saveButton;
    private CData $loadButton;
    private CData $manageButton;
    private CData $validationLabel;
    private CData $detailedValidationLabel;
    private CData $startButton;
    private CData $stopButton;

    private array $validationErrors = [];
    private array $validationResult = [];
    private InputValidator $validator;
    private ConfigurationManager $configManager;

    private $onStartTestCallback = null;
    private $onStopTestCallback = null;
    private $onSaveConfigCallback = null;
    private $onLoadConfigCallback = null;
    private $onManageConfigCallback = null;

    // Currently loaded configuration
    private ?TestConfiguration $currentConfig = null;

    /**
     * Initialize the configuration form
     */
    public function __construct()
    {
        $this->validator = new InputValidator();
        $this->configManager = new ConfigurationManager();
        $this->createForm();
        $this->setupValidation();
    }

    /**
     * Create the form layout with all input fields
     * 
     * @return void
     */
    private function createForm(): void
    {
        $this->form = Form::create();
        Form::setPadded($this->form, true);

        // Configuration Management Section
        $configBox = Box::newHorizontalBox();
        Box::setPadded($configBox, true);

        // Single configuration combobox
        $this->saveConfigCombobox = EditableCombobox::create();
        EditableCombobox::setText($this->saveConfigCombobox, '');
        $this->refreshSaveConfigList();
        Box::append($configBox, $this->saveConfigCombobox, true);

        // Save, Load and Manage buttons
        $this->saveButton = Button::create('Save');
        Box::append($configBox, $this->saveButton, false);

        $this->loadButton = Button::create('Load');
        Box::append($configBox, $this->loadButton, false);

        $this->manageButton = Button::create('Manage');
        Box::append($configBox, $this->manageButton, false);

        Form::append($this->form, 'Configuration:', $configBox, false);

        // Add immediate loading when configuration is selected
        EditableCombobox::onChanged($this->saveConfigCombobox, function($combobox) {
            $this->onConfigurationSelected();
        });

        // Validation Labels
        $this->validationLabel = Label::create('');
        Form::append($this->form, '', $this->validationLabel, false);

        $this->detailedValidationLabel = Label::create('');
        Form::append($this->form, '', $this->detailedValidationLabel, true);

        // Test Control Button Box
        $buttonBox = Box::newHorizontalBox();
        Box::setPadded($buttonBox, true);

        // Start Test Button
        $this->startButton = Button::create('Start Test');
        Box::append($buttonBox, $this->startButton, false);

        // Stop Test Button
        $this->stopButton = Button::create('Stop Test');
        Control::disable($this->stopButton); // Initially disabled
        Box::append($buttonBox, $this->stopButton, false);

        Form::append($this->form, '', $buttonBox, false);

        $this->setupEventHandlers();
    }

    /**
     * Set configuration values to form fields
     * 
     * @param array $config Configuration data
     * @return void
     */


    /**
     * Setup event handlers for form controls
     * 
     * @return void
     */
    private function setupEventHandlers(): void
    {
        // Button click handlers
        Button::onClicked($this->startButton, function($button) {
            $this->onStartTest();
        });

        Button::onClicked($this->stopButton, function($button) {
            $this->onStopTest();
        });

        // Configuration management handlers
        Button::onClicked($this->saveButton, function($button) {
            $this->onSaveConfig();
        });

        Button::onClicked($this->loadButton, function($button) {
            $this->onLoadConfig();
        });

        Button::onClicked($this->manageButton, function($button) {
            $this->onManageConfig();
        });
    }

    /**
     * Handle manage configuration button click
     *
     * @return void
     */
    private function onManageConfig(): void
    {
        // Trigger callback if set
        if ($this->onManageConfigCallback) {
            ($this->onManageConfigCallback)();
        }
    }

    /**
     * Handle configuration selection from dropdown
     *
     * @return void
     */
    private function onConfigurationSelected(): void
    {
        $configName = trim(EditableCombobox::text($this->saveConfigCombobox));

        // Only load if a valid configuration name is selected
        if (!empty($configName)) {
            try {
                // Check if configuration exists
                if ($this->configManager->configurationExists($configName)) {
                    // Load the configuration
                    $config = $this->configManager->loadConfiguration($configName);
                    if ($config) {
                        // Store the loaded configuration
                        $this->currentConfig = $config;

                        // Trigger callback to load configuration in main window
                        if ($this->onLoadConfigCallback) {
                            ($this->onLoadConfigCallback)($config);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Error loading configuration: " . $e->getMessage());
            }
        }
    }

    /**
     * Setup input validation
     * 
     * @return void
     */
    private function setupValidation(): void
    {
        $this->validateInput();
    }

    /**
     * Validate all input fields with comprehensive feedback
     * 
     * @return array Array of validation errors
     */
    public function validateInput(): array
    {
        $config = $this->getConfiguration();
        $this->validationResult = $this->validator->validateConfiguration($config);
        $this->validationErrors = $this->validationResult['errors'];
        
        // Update validation display
        $summary = $this->validator->getValidationSummary($this->validationResult);
        Label::setText($this->validationLabel, $summary);
        
        // Update detailed validation display
        if (!empty($this->validationResult['errors']) || !empty($this->validationResult['warnings'])) {
            $detailedMessage = $this->validator->getDetailedValidationMessage($this->validationResult);
            Label::setText($this->detailedValidationLabel, $detailedMessage);
        } else {
            Label::setText($this->detailedValidationLabel, '');
        }
        
        // Enable/disable start button based on validation
        if ($this->validationResult['isValid']) {
            Control::enable($this->startButton);
        } else {
            Control::disable($this->startButton);
        }
        
        return $this->validationErrors;
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
            $lines[] = $key . ': ' . $value;
        }
        return implode("\n", $lines);
    }

    /**
     * Handle start test button click
     * 
     * @return void
     */
    private function onStartTest(): void
    {
        if (!empty($this->validationErrors)) {
            return;
        }
        
        Control::disable($this->startButton);
        Control::enable($this->stopButton);
        
        if ($this->onStartTestCallback) {
            ($this->onStartTestCallback)($this->getConfiguration());
        }
    }

    /**
     * Handle stop test button click
     * 
     * @return void
     */
    private function onStopTest(): void
    {
        Control::enable($this->startButton);
        Control::disable($this->stopButton);
        
        if ($this->onStopTestCallback) {
            ($this->onStopTestCallback)();
        }
    }

    /**
     * Set callback for start test event
     * 
     * @param callable $callback
     * @return void
     */
    public function setOnStartTestCallback(callable $callback): void
    {
        $this->onStartTestCallback = $callback;
    }

    /**
     * Set callback for stop test event
     * 
     * @param callable $callback
     * @return void
     */
    public function setOnStopTestCallback(callable $callback): void
    {
        $this->onStopTestCallback = $callback;
    }

    /**
     * Get current validation result
     * 
     * @return array Current validation result
     */
    public function getValidationResult(): array
    {
        return $this->validationResult;
    }

    /**
     * Check if configuration is valid
     * 
     * @return bool True if valid
     */
    public function isValid(): bool
    {
        return $this->validationResult['isValid'] ?? false;
    }

    /**
     * Get validation errors for specific field
     * 
     * @param string $fieldName Field name
     * @return string|null Error message or null if no error
     */
    public function getFieldError(string $fieldName): ?string
    {
        return $this->validationResult['fieldErrors'][$fieldName] ?? null;
    }

    /**
     * Get the form control
     * 
     * @return CData
     */
    public function getControl(): CData
    {
        return $this->form;
    }

    /**
     * Enable test execution controls
     * 
     * @return void
     */
    public function enableTestControls(): void
    {
        if (empty($this->validationErrors)) {
            Control::enable($this->startButton);
        }
        Control::disable($this->stopButton);
    }

    /**
     * Disable test execution controls
     * 
     * @return void
     */
    public function disableTestControls(): void
    {
        Control::disable($this->startButton);
        Control::enable($this->stopButton);
    }

    /**
     * Refresh the save configuration combobox list
     * 
     * @return void
     */
    private function refreshSaveConfigList(): void
    {
        try {
            $configurations = $this->configManager->listConfigurations();
            
            // Clear existing items by removing and re-adding the combobox
            // EditableCombobox doesn't have a direct way to clear items
            // We'll work around this limitation by not clearing items directly
            // Instead, we'll just append new items without clearing existing ones
            foreach ($configurations as $config) {
                EditableCombobox::append($this->saveConfigCombobox, $config['name']);
            }
        } catch (Exception $e) {
            error_log("Error refreshing save config list: " . $e->getMessage());
        }
    }

    /**
     * Get current configuration
     *
     * @return TestConfiguration
     */
    public function getConfiguration(): TestConfiguration
    {
        // Return the currently loaded configuration, or a default one if none loaded
        return $this->currentConfig ?? new TestConfiguration();
    }



    /**
     * Handle save configuration button click
     *
     * @return void
     */
    private function onSaveConfig(): void
    {
        $configName = trim(EditableCombobox::text($this->saveConfigCombobox));

        if (empty($configName)) {
            // Show error - configuration name is required
            Label::setText($this->validationLabel, 'Error: Configuration name is required');
            return;
        }

        // Get current configuration
        $config = $this->getConfiguration();

        // Validate configuration before saving
        $errors = $config->validate();
        if (!empty($errors)) {
            Label::setText($this->validationLabel, 'Error: ' . implode(', ', $errors));
            return;
        }

        // Check if configuration exists
        $exists = $this->configManager->configurationExists($configName);

        try {
            // Save configuration (will update if exists, create if not)
            $success = $this->configManager->saveConfiguration($configName, $config);

            if ($success) {
                $action = $exists ? 'updated' : 'saved';
                Label::setText($this->validationLabel, "✓ Configuration '{$configName}' {$action} successfully");

                // Refresh the list
                $this->refreshSaveConfigList();

                // Enable start button
                Control::enable($this->startButton);

                // Trigger callback if set
                if ($this->onSaveConfigCallback) {
                    ($this->onSaveConfigCallback)($configName, $config, $exists);
                }
            } else {
                Label::setText($this->validationLabel, "Error: Failed to save configuration '{$configName}'");
                // Disable start button
                Control::disable($this->startButton);
            }
        } catch (Exception $e) {
            Label::setText($this->validationLabel, 'Error: ' . $e->getMessage());
            // Disable start button
            Control::disable($this->startButton);
        }
    }

    /**
     * Handle load configuration button click
     *
     * @return void
     */
    private function onLoadConfig(): void
    {
        $configName = trim(EditableCombobox::text($this->saveConfigCombobox));

        if (empty($configName)) {
            Label::setText($this->validationLabel, 'Error: Please select or enter a configuration name');
            return;
        }

        try {
            $config = $this->configManager->loadConfiguration($configName);

            if ($config) {
                $this->setConfiguration($config);
                Label::setText($this->validationLabel, "✓ Configuration '{$configName}' loaded successfully");

                // Enable start button
                Control::enable($this->startButton);

                // Trigger callback if set
                if ($this->onLoadConfigCallback) {
                    ($this->onLoadConfigCallback)($config);
                }
            } else {
                Label::setText($this->validationLabel, "Error: Configuration '{$configName}' not found");
                // Disable start button
                Control::disable($this->startButton);
            }
        } catch (Exception $e) {
            Label::setText($this->validationLabel, 'Error: ' . $e->getMessage());
            // Disable start button
            Control::disable($this->startButton);
        }
    }

    /**
     * Handle configuration name change
     *
     * @return void
     */
    private function onConfigNameChanged(): void
    {
        $configName = trim(EditableCombobox::text($this->saveConfigCombobox));

        if (!empty($configName)) {
            $exists = $this->configManager->configurationExists($configName);
            if ($exists) {
                Label::setText($this->validationLabel, "Configuration '{$configName}' found - Save will update, Load will restore");
                // Enable start button when a valid configuration is selected
                Control::enable($this->startButton);
            } else {
                Label::setText($this->validationLabel, "New configuration '{$configName}' - Save will create new");
                // Disable start button when configuration doesn't exist
                Control::disable($this->startButton);
            }
        } else {
            Label::setText($this->validationLabel, '');
            // Disable start button when no configuration is selected
            Control::disable($this->startButton);
        }
    }

    /**
     * Set callback for save configuration event
     * 
     * @param callable $callback
     * @return void
     */
    public function setOnSaveConfigCallback(callable $callback): void
    {
        $this->onSaveConfigCallback = $callback;
    }

    /**
     * Set callback for load configuration event
     *
     * @param callable $callback
     * @return void
     */
    public function setOnLoadConfigCallback(callable $callback): void
    {
        $this->onLoadConfigCallback = $callback;
    }

    /**
     * Set callback for manage configuration event
     *
     * @param callable $callback
     * @return void
     */
    public function setOnManageConfigCallback(callable $callback): void
    {
        $this->onManageConfigCallback = $callback;
    }

    /**
     * Refresh configuration lists
     * 
     * @return void
     */
    public function refreshConfigurationLists(): void
    {
        $this->refreshSaveConfigList();
    }

    /**
     * Set selected configuration in the combobox
     * 
     * @param string $configName
     * @return void
     */
    public function setSelectedConfiguration(string $configName): void
    {
        EditableCombobox::setText($this->saveConfigCombobox, $configName);
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
            $this->onStartTestCallback = null;
            $this->onStopTestCallback = null;
            $this->onSaveConfigCallback = null;
            $this->onLoadConfigCallback = null;
            
            // Clear validation data
            $this->validationErrors = [];
            $this->validationResult = [];
            
            // Note: libui controls are automatically cleaned up when parent is destroyed
            // We don't need to explicitly destroy individual controls
            
        } catch (Exception $e) {
            error_log("Error during ConfigurationForm cleanup: " . $e->getMessage());
        }
    }
}