<?php

declare(strict_types=1);

namespace OhaGui\GUI;

use Kingbes\Libui\Control;
use Kingbes\Libui\Window;
use Kingbes\Libui\Box;
use Kingbes\Libui\Label;
use Kingbes\Libui\Entry;
use Kingbes\Libui\Button;

/**
 * Name input dialog for OHA GUI Tool
 * Provides input dialog for configuration names and other text input
 */
class NameInputDialog extends BaseGUIComponent
{
    private $window;
    private $vbox;
    private $messageLabel;
    private $nameEntry;
    private $okButton;
    private $cancelButton;
    private $errorLabel;
    private $onOkCallback = null;
    private $onCancelCallback = null;

    /**
     * Show name input dialog
     * 
     * @param string $title Dialog title
     * @param string $message Input prompt message
     * @param string $defaultValue Default value for input field
     * @param callable|null $onOk Callback for OK button (receives input value)
     * @param callable|null $onCancel Callback for cancel button
     */
    public function show(string $title, string $message, string $defaultValue = "", $onOk = null, $onCancel = null): void
    {
        $this->onOkCallback = $onOk;
        $this->onCancelCallback = $onCancel;

        $this->createDialog($title, $message, $defaultValue);
        $this->showDialog();
    }

    /**
     * Create the input dialog
     * 
     * @param string $title
     * @param string $message
     * @param string $defaultValue
     */
    private function createDialog(string $title, string $message, string $defaultValue): void
    {
        if ($this->window !== null) {
            $this->cleanup();
        }

        // Create dialog window
        $this->window = Window::create(
            $title,
            400,  // width
            200,  // height
            0     // no menubar
        );

        Window::setMargined($this->window, true);

        // Create layout
        $this->createLayout($message, $defaultValue);
        $this->setupEventHandlers();
    }

    /**
     * Create dialog layout
     * 
     * @param string $message
     * @param string $defaultValue
     */
    private function createLayout(string $message, string $defaultValue): void
    {
        // Create main vertical box
        $this->vbox = Box::newVerticalBox();
        Box::setPadded($this->vbox, true);

        // Create message label
        $this->messageLabel = Label::create($message);
        Box::append($this->vbox, $this->messageLabel, false);

        // Create input field
        $this->nameEntry = Entry::create();
        Entry::setText($this->nameEntry, $defaultValue);
        Box::append($this->vbox, $this->nameEntry, false);

        // Create error display
        $this->errorLabel = Label::create("");
        Box::append($this->vbox, $this->errorLabel, false);

        // Create buttons
        $this->createButtons();

        // Set window content
        Window::setChild($this->window, $this->vbox);

        // Focus on input field
    }

    /**
     * Create dialog buttons
     */
    private function createButtons(): void
    {
        // Create horizontal box for buttons
        $buttonsHBox = Box::newHorizontalBox();
        Box::setPadded($buttonsHBox, true);

        // Add spacer to center buttons
        $spacer1 = Label::create("");
        Box::append($buttonsHBox, $spacer1, true);

        // OK button
        $this->okButton = Button::create("OK");
        $okCallback = function() {
            $this->onOk();
        };
        Button::onClicked($this->okButton, $okCallback);
        Box::append($buttonsHBox, $this->okButton, false);

        // Cancel button
        $this->cancelButton = Button::create("Cancel");
        $cancelCallback = function() {
            $this->onCancel();
        };
        Button::onClicked($this->cancelButton, $cancelCallback);
        Box::append($buttonsHBox, $this->cancelButton, false);

        // Add spacer to center buttons
        $spacer2 = Label::create("");
        Box::append($buttonsHBox, $spacer2, true);

        // Add buttons to main layout
        Box::append($this->vbox, $buttonsHBox, false);
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

        // Entry validation callback
        $entryCallback = function() {
            $this->validateInput();
        };
        Entry::onChanged($this->nameEntry, $entryCallback);
    }

    /**
     * Show the dialog
     */
    private function showDialog(): void
    {
        if ($this->window === null) {
            return;
        }

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
     * Get current input value
     * 
     * @return string
     */
    private function getInputValue(): string
    {
        if ($this->nameEntry === null) {
            return "";
        }

        $textPtr = Entry::text($this->nameEntry);
        return trim($textPtr);
    }

    /**
     * Validate input and show feedback
     */
    private function validateInput(): void
    {
        $value = $this->getInputValue();
        $errors = $this->validateName($value);
        
        if (!empty($errors)) {
            $this->showError(implode('; ', $errors));
            $this->disableOkButton();
        } else {
            $this->clearError();
            $this->enableOkButton();
        }
    }

    /**
     * Validate configuration name
     * 
     * @param string $name
     * @return array
     */
    private function validateName(string $name): array
    {
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Name is required';
            return $errors;
        }
        
        if (strlen($name) < 2) {
            $errors[] = 'Name must be at least 2 characters long';
        }
        
        if (strlen($name) > 50) {
            $errors[] = 'Name must be less than 50 characters';
        }
        
        // Check for invalid characters in filename
        if (preg_match('/[<>:"|?*\/\\\\]/', $name)) {
            $errors[] = 'Name contains invalid characters';
        }
        
        // Check for reserved names
        $reserved = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'];
        if (in_array(strtoupper($name), $reserved)) {
            $errors[] = 'Name is reserved and cannot be used';
        }
        
        return $errors;
    }

    /**
     * Handle OK button click
     */
    private function onOk(): void
    {
        $value = $this->getInputValue();
        $errors = $this->validateName($value);
        
        if (!empty($errors)) {
            $this->showError(implode('; ', $errors));
            return;
        }

        // Call OK callback if set
        if ($this->onOkCallback !== null) {
            ($this->onOkCallback)($value);
        }

        // Close dialog
        $this->hide();
        $this->cleanup();
    }

    /**
     * Handle Cancel button click
     */
    private function onCancel(): void
    {
        // Call cancel callback if set
        if ($this->onCancelCallback !== null) {
            ($this->onCancelCallback)();
        }

        // Close dialog
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
        // Treat window close as cancel
        if ($this->onCancelCallback !== null) {
            ($this->onCancelCallback)();
        }

        $this->hide();
        $this->cleanup();
        return false; // Don't destroy, we handle cleanup
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
     * Enable OK button
     */
    private function enableOkButton(): void
    {
        if ($this->okButton !== null) {
            Control::enable($this->okButton);
        }
    }

    /**
     * Disable OK button
     */
    private function disableOkButton(): void
    {
        if ($this->okButton !== null) {
            Control::disable($this->okButton);
        }
    }

    /**
     * Static method to show save configuration name dialog
     * 
     * @param string $defaultName Default configuration name
     * @param callable $onSave Callback for save (receives name)
     * @param callable|null $onCancel Optional callback for cancellation
     */
    public static function showSaveConfigurationDialog(string $defaultName = "", callable $onSave = null, $onCancel = null): void
    {
        $dialog = new self();
        $message = "Enter a name for this configuration:";
        $dialog->show("保存配置", $message, $defaultName, $onSave, $onCancel);
    }

    /**
     * Static method to show rename dialog
     *
     * @param string $currentName Current name
     * @param callable|null $onRename Callback for rename (receives new name)
     * @param null $onCancel Optional callback for cancellation
     */
    public static function showRenameDialog(string $currentName, callable $onRename = null, $onCancel = null): void
    {
        $dialog = new self();
        $message = "Enter new name:";
        $dialog->show("重命名配置", $message, $currentName, $onRename, $onCancel);
    }

    /**
     * Static method to show generic input dialog
     *
     * @param string $title Dialog title
     * @param string $message Input prompt
     * @param string $defaultValue Default input value
     * @param callable|null $onOk Callback for OK (receives input value)
     * @param null $onCancel Optional callback for cancellation
     */
    public static function showInputDialog(string $title, string $message, string $defaultValue = "", callable $onOk = null, $onCancel = null): void
    {
        $dialog = new self();
        $dialog->show($title, $message, $defaultValue, $onOk, $onCancel);
    }

    /**
     * Cleanup resources
     */
    public function cleanup(): void
    {
        try {
            // Clear references to libui controls
            $this->vbox = null;
            $this->messageLabel = null;
            $this->nameEntry = null;
            $this->okButton = null;
            $this->cancelButton = null;
            $this->errorLabel = null;
            
            // Clear callbacks
            $this->onOkCallback = null;
            $this->onCancelCallback = null;

            // Cleanup window last
            if ($this->window !== null) {
//                Control::destroy($this->window);
                $this->window = null;
            }

        } catch (\Throwable $e) {
            error_log("NameInputDialog cleanup error: " . $e->getMessage());
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
