<?php

namespace OhaGui\GUI;

use Kingbes\Libui\Form;
use Kingbes\Libui\Entry;
use Kingbes\Libui\Combobox;
use Kingbes\Libui\Spinbox;
use Kingbes\Libui\MultilineEntry;
use Kingbes\Libui\Button;
use Kingbes\Libui\Label;
use Kingbes\Libui\Box;
use Kingbes\Libui\Control;
use FFI\CData;
use OhaGui\Models\TestConfiguration;
use OhaGui\Core\InputValidator;
use Exception;

/**
 * Configuration Form component for HTTP load test parameters
 * 
 * Provides input fields for all oha test parameters with validation
 */
class ConfigurationForm
{
    private CData $form;
    private CData $urlEntry;
    private CData $methodCombobox;
    private CData $connectionsSpinbox;
    private CData $durationSpinbox;
    private CData $timeoutSpinbox;
    private CData $headersEntry;
    private CData $bodyEntry;
    private CData $startButton;
    private CData $stopButton;
    private CData $validationLabel;
    private CData $detailedValidationLabel;
    
    private array $validationErrors = [];
    private array $validationResult = [];
    private InputValidator $validator;
    private $onStartTestCallback = null;
    private $onStopTestCallback = null;

    /**
     * Initialize the configuration form
     */
    public function __construct()
    {
        $this->validator = new InputValidator();
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

        // URL Entry
        $this->urlEntry = Entry::create();
        Entry::setText($this->urlEntry, 'http://localhost:8080');
        Form::append($this->form, 'URL:', $this->urlEntry, false);

        // HTTP Method Combobox
        $this->methodCombobox = Combobox::create();
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        foreach ($methods as $method) {
            Combobox::append($this->methodCombobox, $method);
        }
        Combobox::setSelected($this->methodCombobox, 0); // Default to GET
        Form::append($this->form, 'HTTP Method:', $this->methodCombobox, false);

        // Concurrent Connections Spinbox
        $this->connectionsSpinbox = Spinbox::create(1, 1000);
        Spinbox::setValue($this->connectionsSpinbox, 10);
        Form::append($this->form, 'Concurrent Connections:', $this->connectionsSpinbox, false);

        // Duration Spinbox
        $this->durationSpinbox = Spinbox::create(1, 3600);
        Spinbox::setValue($this->durationSpinbox, 10);
        Form::append($this->form, 'Duration (seconds):', $this->durationSpinbox, false);

        // Timeout Spinbox
        $this->timeoutSpinbox = Spinbox::create(1, 300);
        Spinbox::setValue($this->timeoutSpinbox, 30);
        Form::append($this->form, 'Timeout (seconds):', $this->timeoutSpinbox, false);

        // Request Headers MultilineEntry
        $this->headersEntry = MultilineEntry::create();
        MultilineEntry::setText($this->headersEntry, "Content-Type: application/json\nUser-Agent: OHA-GUI-Tool");
        Form::append($this->form, 'Request Headers:', $this->headersEntry, true);

        // Request Body MultilineEntry
        $this->bodyEntry = MultilineEntry::create();
        MultilineEntry::setText($this->bodyEntry, '{"key": "value"}');
        Form::append($this->form, 'Request Body:', $this->bodyEntry, true);

        // Validation Labels
        $this->validationLabel = Label::create('');
        Form::append($this->form, '', $this->validationLabel, false);

        $this->detailedValidationLabel = Label::create('');
        Form::append($this->form, '', $this->detailedValidationLabel, true);

        // Button Box
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
     * Setup event handlers for form controls
     * 
     * @return void
     */
    private function setupEventHandlers(): void
    {
        // URL validation on change
        Entry::onChanged($this->urlEntry, function($entry) {
            $this->validateUrlField();
        });

        // Method change handler
        Combobox::onSelected($this->methodCombobox, function($combobox) {
            $this->updateBodyFieldVisibility();
            $this->validateInput(); // Full validation needed due to method change
        });

        // Numeric field validation
        Spinbox::onChanged($this->connectionsSpinbox, function($spinbox) {
            $this->validateInput();
        });

        Spinbox::onChanged($this->durationSpinbox, function($spinbox) {
            $this->validateInput();
        });

        Spinbox::onChanged($this->timeoutSpinbox, function($spinbox) {
            $this->validateInput();
        });

        // Headers validation
        MultilineEntry::onChanged($this->headersEntry, function($entry) {
            $this->validateHeadersField();
        });

        // Body validation
        MultilineEntry::onChanged($this->bodyEntry, function($entry) {
            $this->validateBodyField();
        });

        // Button click handlers
        Button::onClicked($this->startButton, function($button) {
            $this->onStartTest();
        });

        Button::onClicked($this->stopButton, function($button) {
            $this->onStopTest();
        });
    }

    /**
     * Setup input validation
     * 
     * @return void
     */
    private function setupValidation(): void
    {
        $this->validateInput();
        $this->updateBodyFieldVisibility();
    }

    /**
     * Update body field visibility based on HTTP method
     * 
     * @return void
     */
    private function updateBodyFieldVisibility(): void
    {
        $selectedIndex = Combobox::selected($this->methodCombobox);
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $method = $methods[$selectedIndex] ?? 'GET';
        
        $methodsWithBody = ['POST', 'PUT', 'PATCH'];
        $hasBody = in_array($method, $methodsWithBody);
        
        if ($hasBody) {
            Control::enable($this->bodyEntry);
        } else {
            Control::disable($this->bodyEntry);
            MultilineEntry::setText($this->bodyEntry, '');
        }
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
     * Get current configuration from form fields
     * 
     * @return TestConfiguration
     */
    public function getConfiguration(): TestConfiguration
    {
        $url = Entry::text($this->urlEntry);
        
        $selectedIndex = Combobox::selected($this->methodCombobox);
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $method = $methods[$selectedIndex] ?? 'GET';
        
        $connections = Spinbox::value($this->connectionsSpinbox);
        $duration = Spinbox::value($this->durationSpinbox);
        $timeout = Spinbox::value($this->timeoutSpinbox);
        
        $headersText = MultilineEntry::text($this->headersEntry);
        $headers = $this->parseHeaders($headersText);
        
        $body = MultilineEntry::text($this->bodyEntry);
        
        return new TestConfiguration(
            '', // name will be set when saving
            $url,
            $method,
            $connections,
            $duration,
            $timeout,
            $headers,
            $body
        );
    }

    /**
     * Set configuration values in form fields
     * 
     * @param TestConfiguration $config
     * @return void
     */
    public function setConfiguration(TestConfiguration $config): void
    {
        Entry::setText($this->urlEntry, $config->url);
        
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $methodIndex = array_search(strtoupper($config->method), $methods);
        if ($methodIndex !== false) {
            Combobox::setSelected($this->methodCombobox, $methodIndex);
        }
        
        Spinbox::setValue($this->connectionsSpinbox, $config->concurrentConnections);
        Spinbox::setValue($this->durationSpinbox, $config->duration);
        Spinbox::setValue($this->timeoutSpinbox, $config->timeout);
        
        $headersText = $this->formatHeaders($config->headers);
        MultilineEntry::setText($this->headersEntry, $headersText);
        
        MultilineEntry::setText($this->bodyEntry, $config->body);
        
        $this->updateBodyFieldVisibility();
        $this->validateInput();
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
     * Validate individual URL field
     * 
     * @return void
     */
    public function validateUrlField(): void
    {
        $url = Entry::text($this->urlEntry);
        $result = $this->validator->validateUrl($url);
        
        if (!$result['isValid'] && !empty($result['fieldErrors']['url'])) {
            // Could show field-specific error tooltip or status
            // For now, trigger full validation
            $this->validateInput();
        }
    }

    /**
     * Validate headers field
     * 
     * @return void
     */
    public function validateHeadersField(): void
    {
        $headersText = MultilineEntry::text($this->headersEntry);
        $result = $this->validator->validateHeaders($headersText);
        
        if (!$result['isValid'] && !empty($result['fieldErrors']['headers'])) {
            // Could show field-specific error feedback
            $this->validateInput();
        }
    }

    /**
     * Validate request body field
     * 
     * @return void
     */
    public function validateBodyField(): void
    {
        $body = MultilineEntry::text($this->bodyEntry);
        $selectedIndex = Combobox::selected($this->methodCombobox);
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $method = $methods[$selectedIndex] ?? 'GET';
        
        $result = $this->validator->validateRequestBody($body, $method);
        
        if (!$result['isValid'] && !empty($result['fieldErrors']['body'])) {
            // Could show field-specific error feedback
            $this->validateInput();
        }
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