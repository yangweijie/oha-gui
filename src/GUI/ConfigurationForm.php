<?php

declare(strict_types=1);

namespace OhaGui\GUI;

use Kingbes\Libui\Base as LibuiBase;
use Kingbes\Libui\Box;
use Kingbes\Libui\Label;
use Kingbes\Libui\Entry;
use Kingbes\Libui\Combobox;
use Kingbes\Libui\Spinbox;
use Kingbes\Libui\MultilineEntry;
use Kingbes\Libui\Button;
use Kingbes\Libui\Group;
use Kingbes\Libui\Control;
use OhaGui\Models\TestConfiguration;
use OhaGui\Core\ConfigurationValidator;

/**
 * Configuration form component for OHA GUI Tool
 * Provides input fields for test parameters with validation
 */
class ConfigurationForm extends BaseGUIComponent
{
    private $formGroup;
    private $urlEntry;
    private $methodCombobox;
    private $connectionsSpinbox;
    private $durationSpinbox;
    private $timeoutSpinbox;
    private $headersEntry;
    private $bodyEntry;
    private $startButton;
    private $stopButton;
    private $saveButton;
    private $errorLabel;
    
    private ?ConfigurationValidator $validator = null;
    private $onStartTestCallback = null;
    private $onStopTestCallback = null;
    private $onSaveConfigCallback = null;

    /**
     * Initialize the configuration form
     */
    public function __construct()
    {
        $this->validator = new ConfigurationValidator();
    }

    /**
     * Helper method to cast control to uiControl*
     * 
     * @param mixed $control
     * @return mixed
     */
    protected function castControl($control)
    {
        return parent::castControl($control);
    }

    /**
     * Create the configuration form UI
     * 
     * @return mixed libui control
     */
    public function createForm()
    {
        // Create main form group
        $this->formGroup = Group::create("输入 (Input)");
        Group::setMargined($this->formGroup, true);

        // Create form layout
        $formBox = Box::newVerticalBox();
        Box::setPadded($formBox, true);

        // Create form fields
        $this->createUrlField($formBox);
        $this->createMethodAndConnectionsRow($formBox);
        $this->createDurationAndTimeoutRow($formBox);
        $this->createHeadersField($formBox);
        $this->createBodyField($formBox);
        $this->createButtonsRow($formBox);
        $this->createErrorDisplay($formBox);

        // Set form content
        Group::setChild($this->formGroup, $formBox);

        return $this->formGroup;
    }

    /**
     * Create URL input field
     */
    private function createUrlField($parent): void
    {
        // URL row
        $urlHBox = Box::newHorizontalBox();
        Box::setPadded($urlHBox, true);

        $urlLabel = Label::create("URL:");
        Box::append($urlHBox, $urlLabel, false);

        $this->urlEntry = Entry::create();
        Entry::setText($this->urlEntry, "http://example.com");
        
        // Add real-time validation callback
        $urlValidationCallback = function() {
            $this->validateUrlField();
        };
        Entry::onChanged($this->urlEntry, $urlValidationCallback);
        
        Box::append($urlHBox, $this->urlEntry, true);

        Box::append($parent, $urlHBox, false);
    }

    /**
     * Create method and connections row
     */
    private function createMethodAndConnectionsRow($parent): void
    {
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
        Combobox::setSelected($this->methodCombobox, 0); // Default to GET
        Box::append($methodConnHBox, $this->methodCombobox, false);

        // Connections
        $connectionsLabel = Label::create("Connections:");
        Box::append($methodConnHBox, $connectionsLabel, false);

        $this->connectionsSpinbox = Spinbox::create(1, 1000);
        Spinbox::setValue($this->connectionsSpinbox, 1);
        Box::append($methodConnHBox, $this->connectionsSpinbox, false);

        Box::append($parent, $methodConnHBox, false);
    }

    /**
     * Create duration and timeout row
     */
    private function createDurationAndTimeoutRow($parent): void
    {
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

        Box::append($parent, $durTimeoutHBox, false);
    }

    /**
     * Create headers input field
     */
    private function createHeadersField($parent): void
    {
        $headersLabel = Label::create("Request Headers (one per line, format: Header: Value):");
        Box::append($parent, $headersLabel, false);

        $this->headersEntry = MultilineEntry::create();
        MultilineEntry::setText($this->headersEntry, "Content-Type: application/json\nUser-Agent: OHA-GUI-Tool");
        Box::append($parent, $this->headersEntry, false);
    }

    /**
     * Create body input field
     */
    private function createBodyField($parent): void
    {
        $bodyLabel = Label::create("Request Body:");
        Box::append($parent, $bodyLabel, false);

        $this->bodyEntry = MultilineEntry::create(true); // true for stretchable multiline entry
        MultilineEntry::setText($this->bodyEntry, "");
        
        // Add real-time validation callback for body
        $bodyValidationCallback = function() {
            $this->validateBodyField();
        };
        MultilineEntry::onChanged($this->bodyEntry, $bodyValidationCallback);
        
        Box::append($parent, $this->bodyEntry, true); // true for stretching
    }

    /**
     * Create buttons row
     */
    private function createButtonsRow($parent): void
    {
        $buttonsHBox = Box::newHorizontalBox();
        Box::setPadded($buttonsHBox, true);

        // Start button
        $this->startButton = Button::create("开始测试");
        $startCallback = function() {
            $this->onStartTest();
        };
        Button::onClicked($this->startButton, $startCallback, null);
        Box::append($buttonsHBox, $this->startButton, false);

        // Stop button
        $this->stopButton = Button::create("停止");
        Control::disable($this->stopButton); // Initially disabled
        $stopCallback = function() {
            $this->onStopTest();
        };
        Button::onClicked($this->stopButton, $stopCallback, null);
        Box::append($buttonsHBox, $this->stopButton, false);

        // Save configuration button
        $this->saveButton = Button::create("保存配置");
        $saveCallback = function() {
            $this->onSaveConfig();
        };
        Button::onClicked($this->saveButton, $saveCallback, null);
        Box::append($buttonsHBox, $this->saveButton, false);

        Box::append($parent, $buttonsHBox, false);
    }

    /**
     * Create error display
     */
    private function createErrorDisplay($parent): void
    {
        $this->errorLabel = Label::create("");
        Box::append($parent, $this->errorLabel, false);
    }

    /**
     * Get current configuration from form fields
     * 
     * @return TestConfiguration
     */
    public function getConfiguration(): TestConfiguration
    {
        $config = new TestConfiguration();
        
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
        $config->headers = $this->parseHeaders($headersPtr);

        // Get body
        $bodyPtr = MultilineEntry::text($this->bodyEntry);
        $this->freeText($bodyPtr);

        return $config;
    }

    /**
     * Set configuration values in form fields
     * 
     * @param TestConfiguration $config
     */
    public function setConfiguration(TestConfiguration $config): void
    {
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

        // Clear any error messages
        $this->clearError();
    }

    /**
     * Set form fields to read-only mode
     * 
     * @param bool $readOnly
     */
    public function setReadOnly(bool $readOnly): void
    {
        // Set URL entry read-only
        if ($readOnly) {
            Control::disable($this->urlEntry);
        } else {
            Control::enable($this->urlEntry);
        }

        // Set method combobox read-only
        if ($readOnly) {
            Control::disable($this->methodCombobox);
        } else {
            Control::enable($this->methodCombobox);
        }

        // Set numeric spinboxes read-only
        if ($readOnly) {
            Control::disable($this->connectionsSpinbox);
            Control::disable($this->durationSpinbox);
            Control::disable($this->timeoutSpinbox);
        } else {
            Control::enable($this->connectionsSpinbox);
            Control::enable($this->durationSpinbox);
            Control::enable($this->timeoutSpinbox);
        }

        // Set headers entry read-only
        if ($readOnly) {
            Control::disable($this->headersEntry);
        } else {
            Control::enable($this->headersEntry);
        }

        // Set body entry read-only
        if ($readOnly) {
            Control::disable($this->bodyEntry);
        } else {
            Control::enable($this->bodyEntry);
        }
    }

    /**
     * Validate current form input
     * 
     * @return array Array of validation errors (empty if valid)
     */
    public function validateInput(): array
    {
        $config = $this->getConfiguration();
        return $config->validate();
    }

    /**
     * Validate individual field and show immediate feedback
     * 
     * @param string $fieldName Field to validate
     * @return array Validation errors for the field
     */
    public function validateField(string $fieldName): array
    {
        $config = $this->getConfiguration();
        $configArray = $config->toArray();
        
        // Use the comprehensive validator for detailed field validation
        $allErrors = $this->validator->validateConfiguration($configArray);
        
        // Filter errors for the specific field
        $fieldErrors = [];
        foreach ($allErrors as $error) {
            if (stripos($error, $fieldName) !== false || 
                ($fieldName === 'url' && stripos($error, 'URL') !== false) ||
                ($fieldName === 'method' && stripos($error, 'method') !== false) ||
                ($fieldName === 'concurrentConnections' && stripos($error, 'connection') !== false) ||
                ($fieldName === 'duration' && stripos($error, 'duration') !== false) ||
                ($fieldName === 'timeout' && stripos($error, 'timeout') !== false) ||
                ($fieldName === 'headers' && stripos($error, 'header') !== false) ||
                ($fieldName === 'body' && stripos($error, 'body') !== false)) {
                $fieldErrors[] = $error;
            }
        }
        
        return $fieldErrors;
    }

    /**
     * Validate URL field specifically with detailed feedback
     * 
     * @param string $url URL to validate
     * @return array Validation errors
     */
    public function validateUrl(string $url): array
    {
        $errors = [];
        
        if (empty(trim($url))) {
            $errors[] = 'URL is required';
            return $errors;
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = 'URL format is invalid';
        }
        
        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['scheme']) || !isset($parsedUrl['host'])) {
            $errors[] = 'URL must include scheme (http/https) and host';
        }
        
        if (isset($parsedUrl['scheme']) && !in_array($parsedUrl['scheme'], ['http', 'https'])) {
            $errors[] = 'URL scheme must be http or https';
        }
        
        return $errors;
    }

    /**
     * Validate JSON body format
     * 
     * @param string $body JSON body to validate
     * @param string $method HTTP method
     * @return array Validation errors
     */
    public function validateJsonBody(string $body, string $method): array
    {
        $errors = [];
        
        if (empty(trim($body))) {
            return $errors; // Empty body is valid
        }
        
        $methodsWithBody = ['POST', 'PUT', 'PATCH'];
        if (!in_array(strtoupper($method), $methodsWithBody)) {
            return $errors; // Body validation not needed for GET/DELETE
        }
        
        // Try to parse as JSON first
        json_decode($body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If not valid JSON, check if it's valid form data
            if (!$this->isValidFormData($body)) {
                $errors[] = 'Request body must be valid JSON or form data for ' . strtoupper($method) . ' requests';
                $errors[] = 'JSON error: ' . json_last_error_msg();
            }
        }
        
        return $errors;
    }

    /**
     * Check if string is valid form data format
     * 
     * @param string $string
     * @return bool
     */
    private function isValidFormData(string $string): bool
    {
        // Form data must contain at least one = sign for key=value pairs
        if (!str_contains($string, '=')) {
            return false;
        }
        
        // Simple check for form data format (key=value&key=value)
        return preg_match('/^[^=&]+(=[^&]*)?(&[^=&]+(=[^&]*)?)*$/', $string) === 1;
    }

    /**
     * Handle start test button click
     */
    public function onStartTest(): void
    {
        // Validate all fields with comprehensive feedback
        if (!$this->validateAllFields()) {
            return;
        }

        // Validate that a configuration name is selected (not "Select Config")
        if ($this->onStartTestCallback !== null) {
            $config = $this->getConfiguration();
            if (empty($config->name) || $config->name === 'Select Config') {
                $this->showValidationError("Please select a configuration name before starting the test");
                return;
            }
        }

        $this->showSuccess("Starting test...");

        // Disable start button, enable stop button
        Control::disable($this->startButton);
        Control::enable($this->stopButton);

        // Call callback if set
        if ($this->onStartTestCallback !== null) {
            ($this->onStartTestCallback)($this->getConfiguration());
        }
    }

    /**
     * Handle stop test button click
     */
    public function onStopTest(): void
    {
        // Enable start button, disable stop button
        Control::enable($this->startButton);
        Control::disable($this->stopButton);

        // Call callback if set
        if ($this->onStopTestCallback !== null) {
            ($this->onStopTestCallback)();
        }
    }

    /**
     * Handle save configuration button click
     */
    public function onSaveConfig(): void
    {
        // Validate all fields with comprehensive feedback
        if (!$this->validateAllFields()) {
            return;
        }

        $this->showSuccess("Configuration is valid, saving...");

        // Call callback if set
        if ($this->onSaveConfigCallback !== null) {
            ($this->onSaveConfigCallback)($this->getConfiguration());
        }
    }

    /**
     * Set start test callback
     * 
     * @param callable $callback
     */
    public function setOnStartTestCallback(callable $callback): void
    {
        $this->onStartTestCallback = $callback;
    }

    /**
     * Set stop test callback
     * 
     * @param callable $callback
     */
    public function setOnStopTestCallback(callable $callback): void
    {
        $this->onStopTestCallback = $callback;
    }

    /**
     * Set save configuration callback
     * 
     * @param callable $callback
     */
    public function setOnSaveConfigCallback(callable $callback): void
    {
        $this->onSaveConfigCallback = $callback;
    }

    /**
     * Show error message
     * 
     * @param string $message
     */
    public function showError(string $message): void
    {
        Label::setText($this->errorLabel, "Error: " . $message);
    }

    /**
     * Clear error message
     */
    public function clearError(): void
    {
        Label::setText($this->errorLabel, "");
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
        $lines = explode("\n", $headersText);
        
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
        return implode("\n", $lines);
    }

    /**
     * Reset test buttons to initial state
     */
    public function resetTestButtons(): void
    {
        Control::enable($this->startButton);
        Control::disable($this->stopButton);
    }

    /**
     * Validate URL field in real-time
     */
    private function validateUrlField(): void
    {
        $urlPtr = Entry::text($this->urlEntry);
        $errors = $this->validateUrl($urlPtr);
        if (!empty($errors)) {
            $this->showError(implode('; ', $errors));
        } else {
            $this->clearError();
        }
    }

    /**
     * Validate body field in real-time
     */
    private function validateBodyField(): void
    {
        // Get current method
        $methodIndex = Combobox::selected($this->methodCombobox);
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $method = $methods[$methodIndex] ?? 'GET';
        
        // Get body text
        $bodyPtr = MultilineEntry::text($this->bodyEntry);

        $errors = $this->validateJsonBody($bodyPtr, $method);
        if (!empty($errors)) {
            $this->showError(implode('; ', $errors));
        } else {
            $this->clearError();
        }
    }

    /**
     * Show validation error with improved formatting
     * 
     * @param string $message
     */
    public function showValidationError(string $message): void
    {
        Label::setText($this->errorLabel, "⚠️ " . $message);
    }

    /**
     * Show success message
     * 
     * @param string $message
     */
    public function showSuccess(string $message): void
    {
        Label::setText($this->errorLabel, "✅ " . $message);
    }

    /**
     * Validate all fields and show comprehensive feedback
     * 
     * @return bool True if all fields are valid
     */
    public function validateAllFields(): bool
    {
        $errors = $this->validateInput();
        
        if (!empty($errors)) {
            $this->showValidationError(implode('; ', $errors));
            return false;
        }
        
        $this->clearError();
        return true;
    }

    /**
     * Set configuration values in form fields without changing read-only state
     * This method allows updating form values even when controls are disabled
     * 
     * @param TestConfiguration $config
     */
    public function setConfigurationWithoutStateChange(TestConfiguration $config): void
    {
        // Enable all controls temporarily to set values
        $urlEnabled = Control::enabled($this->urlEntry);
        $methodEnabled = Control::enabled($this->methodCombobox);
        $connectionsEnabled = Control::enabled($this->connectionsSpinbox);
        $durationEnabled = Control::enabled($this->durationSpinbox);
        $timeoutEnabled = Control::enabled($this->timeoutSpinbox);
        $headersEnabled = Control::enabled($this->headersEntry);
        $bodyEnabled = Control::enabled($this->bodyEntry);
        
        // Temporarily enable controls to set values
        Control::enable($this->urlEntry);
        Control::enable($this->methodCombobox);
        Control::enable($this->connectionsSpinbox);
        Control::enable($this->durationSpinbox);
        Control::enable($this->timeoutSpinbox);
        Control::enable($this->headersEntry);
        Control::enable($this->bodyEntry);

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

        // Restore previous enabled/disabled state
        if (!$urlEnabled) Control::disable($this->urlEntry);
        if (!$methodEnabled) Control::disable($this->methodCombobox);
        if (!$connectionsEnabled) Control::disable($this->connectionsSpinbox);
        if (!$durationEnabled) Control::disable($this->durationSpinbox);
        if (!$timeoutEnabled) Control::disable($this->timeoutSpinbox);
        if (!$headersEnabled) Control::disable($this->headersEntry);
        if (!$bodyEnabled) Control::disable($this->bodyEntry);

        // Clear any error messages
        $this->clearError();
    }

    /**
     * Cleanup resources
     */
    public function cleanup(): void
    {
        try {
            // libui handles control cleanup automatically when parent is destroyed
            $this->formGroup = null;
            $this->urlEntry = null;
            $this->methodCombobox = null;
            $this->connectionsSpinbox = null;
            $this->durationSpinbox = null;
            $this->timeoutSpinbox = null;
            $this->headersEntry = null;
            $this->bodyEntry = null;
            $this->startButton = null;
            $this->stopButton = null;
            $this->saveButton = null;
            $this->errorLabel = null;
            
            // Clear callbacks
            $this->onStartTestCallback = null;
            $this->onStopTestCallback = null;
            $this->onSaveConfigCallback = null;
            
            // Clear validator reference
            $this->validator = null;
            
        } catch (\Throwable $e) {
            error_log("ConfigurationForm cleanup error: " . $e->getMessage());
        }
    }
}