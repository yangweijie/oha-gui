<?php

declare(strict_types=1);

namespace OhaGui\GUI;

use Kingbes\Libui\Control;
use Kingbes\Libui\Window;
use Kingbes\Libui\Box;
use Kingbes\Libui\Label;
use Kingbes\Libui\Entry;
use Kingbes\Libui\Combobox;
use Kingbes\Libui\Spinbox;
use Kingbes\Libui\MultilineEntry;
use Kingbes\Libui\Button;
use OhaGui\Models\TestConfiguration;
use OhaGui\Core\ConfigurationManager;
use OhaGui\Core\ConfigurationValidator;
use OhaGui\Utils\WindowHelper;
use Throwable;

/**
 * Configuration dialog for adding and editing configurations
 * Provides popup dialog for configuration management
 */
class ConfigurationDialog extends BaseGUIComponent
{
    private $window;
    private $vbox;
    private $nameEntry;
    private $urlEntry;
    private $methodCombobox;
    private $connectionsSpinbox;
    private $durationSpinbox;
    private $timeoutSpinbox;
    private $headersEntry;
    private $bodyEntry;
    private $errorLabel;

    private ?ConfigurationManager $configManager;
    private ?ConfigurationValidator $validator;
    private $onSaveCallback = null;
    private bool $isEditMode = false;
    private ?TestConfiguration $editingConfig;

    /**
     * Initialize the configuration dialog
     */
    public function __construct()
    {
        $this->configManager = new ConfigurationManager();
        $this->validator = new ConfigurationValidator();
    }

    /**
     * Show dialog for adding new configuration
     */
    public function showAddDialog(): void
    {
        $this->isEditMode = false;
        $this->editingConfig = null;
        $this->createDialog("新增配置");
        $this->clearForm();
        $this->show();
    }

    /**
     * Show dialog for editing existing configuration
     * 
     * @param TestConfiguration $config
     */
    public function showEditDialog(TestConfiguration $config): void
    {
        $this->isEditMode = true;
        $this->editingConfig = $config;
        $this->createDialog("编辑配置");
        $this->populateForm($config);
        $this->show();
    }

    /**
     * Create the dialog window and form
     * 
     * @param string $title
     */
    private function createDialog(string $title): void
    {
        if ($this->window !== null) {
            $this->cleanup();
        }

        // Create dialog window
        $this->window = Window::create(
            $title,
            500,  // width
            600,  // height
            0     // no menubar
        );

        Window::setMargined($this->window, true);

        // Create main layout
        $this->createLayout();
        $this->setupEventHandlers();
    }

    /**
     * Create the dialog layout
     */
    private function createLayout(): void
    {
        // Create main vertical box
        $this->vbox = Box::newVerticalBox();
        Box::setPadded($this->vbox, true);

        // Create form fields
        $this->createFormFields();
        $this->createButtons();
        $this->createErrorDisplay();

        // Set window content
        Window::setChild($this->window, $this->vbox);
    }

    /**
     * Create form input fields
     */
    private function createFormFields(): void
    {
        // Configuration name
        $nameLabel = Label::create("Configuration Name:");
        Box::append($this->vbox, $nameLabel, false);

        $this->nameEntry = Entry::create();
        Box::append($this->vbox, $this->nameEntry, false);

        // URL
        $urlLabel = Label::create("URL:");
        Box::append($this->vbox, $urlLabel, false);

        $this->urlEntry = Entry::create();
        Entry::setText($this->urlEntry, "http://example.com");
        Box::append($this->vbox, $this->urlEntry, false);

        // Method and connections row
        $methodConnHBox = Box::newHorizontalBox();
        Box::setPadded($methodConnHBox, true);

        // HTTP Method
        $methodLabel = Label::create("Method:");
        Box::append($methodConnHBox, $methodLabel, false);

        $this->methodCombobox = Combobox::create();
        Combobox::append($this->methodCombobox, "GET");
        Combobox::append($this->methodCombobox, "POST");
        Combobox::append($this->methodCombobox, "PUT");
        Combobox::append($this->methodCombobox, "DELETE");
        Combobox::append($this->methodCombobox, "PATCH");
        Combobox::setSelected($this->methodCombobox, 0);
        Box::append($methodConnHBox, $this->methodCombobox, false);

        // Connections
        $connectionsLabel = Label::create("Connections:");
        Box::append($methodConnHBox, $connectionsLabel, false);

        $this->connectionsSpinbox = Spinbox::create(1, 1000);
        Spinbox::setValue($this->connectionsSpinbox, 1);
        Box::append($methodConnHBox, $this->connectionsSpinbox, false);

        Box::append($this->vbox, $methodConnHBox, false);

        // Duration and timeout row
        $durTimeoutHBox = Box::newHorizontalBox();
        Box::setPadded($durTimeoutHBox, true);

        // Duration
        $durationLabel = Label::create("Duration (s):");
        Box::append($durTimeoutHBox, $durationLabel, false);

        $this->durationSpinbox = Spinbox::create(1, 3600);
        Spinbox::setValue($this->durationSpinbox, 10);
        Box::append($durTimeoutHBox, $this->durationSpinbox, false);

        // Timeout
        $timeoutLabel = Label::create("Timeout (s):");
        Box::append($durTimeoutHBox, $timeoutLabel, false);

        $this->timeoutSpinbox = Spinbox::create(1, 300);
        Spinbox::setValue($this->timeoutSpinbox, 30);
        Box::append($durTimeoutHBox, $this->timeoutSpinbox, false);

        Box::append($this->vbox, $durTimeoutHBox, false);

        // Headers
        $headersLabel = Label::create("Request Headers (one per line, format: Header: Value):");
        Box::append($this->vbox, $headersLabel, false);

        $this->headersEntry = MultilineEntry::create();
        MultilineEntry::setText($this->headersEntry, "Content-Type: application/json");
        Box::append($this->vbox, $this->headersEntry, true);

        // Body
        $bodyLabel = Label::create("Request Body:");
        Box::append($this->vbox, $bodyLabel, false);

        $this->bodyEntry = MultilineEntry::create();
        MultilineEntry::setText($this->bodyEntry, "");
        Box::append($this->vbox, $this->bodyEntry, true);
    }

    /**
     * Create dialog buttons
     */
    private function createButtons(): void
    {
        $buttonsHBox = Box::newHorizontalBox();
        Box::setPadded($buttonsHBox, true);

        // Save button
        $saveButton = Button::create("Save");
        $saveCallback = function() {
            $this->onSave();
        };
        Button::onClicked($saveButton, $saveCallback);
        Box::append($buttonsHBox, $saveButton, false);

        // Cancel button
        $cancelButton = Button::create("Cancel");
        $cancelCallback = function() {
            $this->onCancel();
        };
        Button::onClicked($cancelButton, $cancelCallback);
        Box::append($buttonsHBox, $cancelButton, false);

        Box::append($this->vbox, $buttonsHBox, false);
    }

    /**
     * Create error display
     */
    private function createErrorDisplay(): void
    {
        $this->errorLabel = Label::create("");
        Box::append($this->vbox, $this->errorLabel, false);
    }

    /**
     * Setup event handlers
     */
    private function setupEventHandlers(): void
    {
        // Window closing callback
        $closingCallback = function() {
            return $this->onClosing();
        };
        Window::onClosing($this->window, $closingCallback);
    }

    /**
     * Show the dialog
     */
    private function show(): void
    {
        if ($this->window === null) {
            return;
        }
        // Center window
        $this->centerWindow();

        Control::show($this->window);
    }

    /**
     * Hide the dialog
     */
    private function hide(): void
    {
        if ($this->window === null) {
            return;
        }
        Control::hide($this->window);
    }

    /**
     * Clear form fields
     */
    private function clearForm(): void
    {
        Entry::setText($this->nameEntry, "");
        Entry::setText($this->urlEntry, "http://example.com");
        Combobox::setSelected($this->methodCombobox, 0);
        Spinbox::setValue($this->connectionsSpinbox, 1);
        Spinbox::setValue($this->durationSpinbox, 10);
        Spinbox::setValue($this->timeoutSpinbox, 30);
        MultilineEntry::setText($this->headersEntry, "Content-Type: application/json");
        MultilineEntry::setText($this->bodyEntry, "");
        $this->clearError();
    }

    /**
     * Populate form with configuration data
     * 
     * @param TestConfiguration $config
     */
    private function populateForm(TestConfiguration $config): void
    {
        // Set name (if editing, use the current name)
        Entry::setText($this->nameEntry, $config->name ?? "");

        // Set URL
        Entry::setText($this->urlEntry, $config->url);

        // Set method
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $methodIndex = array_search($config->method, $methods);
        if ($methodIndex !== false) {
            Combobox::setSelected($this->methodCombobox, $methodIndex);
        }

        // Set numeric values
        Spinbox::setValue($this->connectionsSpinbox, $config->concurrentConnections);
        Spinbox::setValue($this->durationSpinbox, $config->duration);
        Spinbox::setValue($this->timeoutSpinbox, $config->timeout);

        // Set headers
        $headersText = $this->formatHeaders($config->headers);
        MultilineEntry::setText($this->headersEntry, $headersText);

        // Set body
        MultilineEntry::setText($this->bodyEntry, $config->body);

        $this->clearError();
    }

    /**
     * Get configuration from form fields
     * 
     * @return TestConfiguration
     */
    private function getConfigurationFromForm(): TestConfiguration
    {
        $config = new TestConfiguration();

        // Get name
        $namePtr = Entry::text($this->nameEntry);
        $config->name = $namePtr;

        // Get URL
        $urlPtr = Entry::text($this->urlEntry);
        $config->url = $urlPtr;

        // Get method
        $methodIndex = Combobox::selected($this->methodCombobox);
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $config->method = $methods[$methodIndex] ?? 'GET';

        // Get numeric values
        $config->concurrentConnections = Spinbox::value($this->connectionsSpinbox);
        $config->duration = Spinbox::value($this->durationSpinbox);
        $config->timeout = Spinbox::value($this->timeoutSpinbox);

        // Get headers
        $headersPtr = MultilineEntry::text($this->headersEntry);
        $headersText = $headersPtr;
        $config->headers = $this->parseHeaders($headersText);

        // Get body
        $bodyPtr = MultilineEntry::text($this->bodyEntry);
        $config->body = $bodyPtr;

        return $config;
    }

    /**
     * Handle save button click
     */
    public function onSave(): void
    {
        try {
            // Check if validator is available
            if ($this->validator === null) {
                $this->showError("Validator not available. Please try again.");
                return;
            }
            
            $config = $this->getConfigurationFromForm();

            // Validate configuration
            $errors = $this->validator->validateConfiguration([
                'name'=>$config->name,
                'body'=>$config->body,
                'url'=>$config->url,
                'method'=>$config->method,
                'concurrentConnections'=>$config->concurrentConnections,
                'duration'=>$config->duration,
                'timeout'=>$config->timeout,
                'headers'=>$config->headers,
            ]);
            if (!empty($errors)) {
                $this->showError(implode("\n", $errors));
                return;
            }

            // Validate name
            if (empty($config->name)) {
                $this->showError("Configuration name is required");
                return;
            }

            // Check if name already exists (for add mode)
            if (!$this->isEditMode) {
                $existingConfigs = $this->configManager->listConfigurations();
                if (in_array($config->name, $existingConfigs)) {
                    $this->showError("Configuration name already exists");
                    return;
                }
            }

            // Save configuration
            $success = $this->configManager->saveConfiguration($config->name, $config);
            
            if ($success) {
                // Call callback if set
                if ($this->onSaveCallback !== null) {
                    ($this->onSaveCallback)($config->name);
                }

                // Close dialog
                $this->hide();
                $this->cleanup();
            } else {
                $this->showError("Failed to save configuration");
            }

        } catch (Throwable $e) {
            $this->showError("Error saving configuration: " . $e->getMessage());
        }
    }

    /**
     * Handle cancel button click
     */
    public function onCancel(): void
    {
        $this->hide();
        $this->cleanup();
    }

    /**
     * Handle window closing
     * 
     * @return bool
     */
    public function onClosing(): bool
    {
        $this->hide();
        $this->cleanup();
        return false; // Don't destroy, just hide
    }

    /**
     * Show error message
     * 
     * @param string $message
     */
    private function showError(string $message): void
    {
        if ($this->errorLabel !== null) {
            Label::setText($this->errorLabel, "Error: " . $message);
        }
    }

    /**
     * Clear error message
     */
    private function clearError(): void
    {
        if ($this->errorLabel !== null) {
            Label::setText($this->errorLabel, "");
        }
    }

    /**
     * Parse headers text into array
     * 
     * @param string $headersText
     * @return array
     */
    private function parseHeaders(string $headersText): array
    {
        $headers = [];
        $lines = explode(PHP_EOL, $headersText);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[trim($parts[0])] = trim($parts[1]);
            }
        }
        
        return $headers;
    }

    /**
     * Format headers array into text
     * 
     * @param array $headers
     * @return string
     */
    private function formatHeaders(array $headers): string
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        return implode(PHP_EOL, $lines);
    }

    /**
     * Set save callback
     * 
     * @param callable $callback
     */
    public function setOnSaveCallback(callable $callback): void
    {
        $this->onSaveCallback = $callback;
    }

    /**
     * Center the window on the screen
     */
    private function centerWindow(): void
    {
        if ($this->window === null) {
            return;
        }
        
        // Use WindowHelper to center the window
        try {
            WindowHelper::centerWindow($this->window);
        } catch (Throwable $e) {
            // Ignore errors in window centering
            error_log("Failed to center window: " . $e->getMessage());
        }
    }

    /**
     * Cleanup resources
     */
    public function cleanup(): void
    {
        try {
            // Clear references
            $this->configManager = null;
            $this->validator = null;
            $this->editingConfig = null;
            $this->onSaveCallback = null;

            // Cleanup window last
            if ($this->window !== null) {
//                Control::destroy($this->window);
                $this->window = null;
            }

        } catch (Throwable $e) {
            error_log("ConfigurationDialog cleanup error: " . $e->getMessage());
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