<?php

namespace OhaGui\Utils;

use Kingbes\Libui\Window;
use Kingbes\Libui\Entry;
use Kingbes\Libui\Button;
use Kingbes\Libui\Label;
use Kingbes\Libui\Box;
use Kingbes\Libui\Group;
use Kingbes\Libui\Control;
use FFI\CData;

/**
 * Dialog Manager for handling various dialog operations
 * 
 * Provides standardized dialog functionality for the application
 */
class DialogManager
{
    private CData $parentWindow;

    public function __construct(CData $parentWindow)
    {
        $this->parentWindow = $parentWindow;
    }

    /**
     * Show a simple information message box
     * 
     * @param string $title Dialog title
     * @param string $message Dialog message
     * @return void
     */
    public function showInfoDialog(string $title, string $message): void
    {
        Window::msgBox($this->parentWindow, $title, $message);
    }

    /**
     * Show an error message box
     * 
     * @param string $title Dialog title
     * @param string $message Error message
     * @return void
     */
    public function showErrorDialog(string $title, string $message): void
    {
        Window::msgBoxError($this->parentWindow, $title, $message);
    }

    /**
     * Show a confirmation dialog with Yes/No options
     * 
     * @param string $title Dialog title
     * @param string $message Confirmation message
     * @param callable $onConfirm Callback for confirmation
     * @param callable|null $onCancel Callback for cancellation
     * @param string $confirmText Text for confirm button (default: "Yes")
     * @param string $cancelText Text for cancel button (default: "No")
     * @return void
     */
    public function showConfirmationDialog(string $title, string $message, callable $onConfirm, ?callable $onCancel = null, string $confirmText = 'Yes', string $cancelText = 'No'): void
    {
        // Create a confirmation window with better sizing
        $confirmWindow = Window::create($title, 450, 180, false);
        Window::setMargined($confirmWindow, true);

        $mainBox = Box::newVerticalBox();
        Box::setPadded($mainBox, true);

        // Add icon/warning indicator if possible
        $headerBox = Box::newHorizontalBox();
        Box::setPadded($headerBox, true);
        
        // Add warning icon (if available) or warning text
        $warningLabel = Label::create('⚠️');
        Box::append($headerBox, $warningLabel, false);
        
        $titleLabel = Label::create($title);
        Box::append($headerBox, $titleLabel, true);
        
        Box::append($mainBox, $headerBox, false);

        // Add message label with word wrapping support
        $messageLabel = Label::create($message);
        Box::append($mainBox, $messageLabel, true);

        // Add spacing
        $spacerLabel = Label::create('');
        Box::append($mainBox, $spacerLabel, false);

        // Add button box
        $buttonBox = Box::newHorizontalBox();
        Box::setPadded($buttonBox, true);

        // Add some spacing before buttons
        $leftSpacer = Label::create('');
        Box::append($buttonBox, $leftSpacer, true);

        $confirmButton = Button::create($confirmText);
        $cancelButton = Button::create($cancelText);

        // Style the confirm button as primary action
        Button::onClicked($confirmButton, function($button) use ($confirmWindow, $onConfirm) {
            Control::destroy($confirmWindow);
            if ($onConfirm) {
                $onConfirm();
            }
        });

        Button::onClicked($cancelButton, function($button) use ($confirmWindow, $onCancel) {
            Control::destroy($confirmWindow);
            if ($onCancel) {
                $onCancel();
            }
        });

        Box::append($buttonBox, $cancelButton, false);
        Box::append($buttonBox, $confirmButton, false);
        Box::append($mainBox, $buttonBox, false);

        Window::setChild($confirmWindow, $mainBox);
        Control::show($confirmWindow);
        
        // Set focus on cancel button by default for safety
        if (method_exists('Kingbes\Libui\Button', 'focus')) {
            Button::focus($cancelButton);
        }
    }

    /**
     * Show a text input dialog with enhanced validation
     * 
     * @param string $title Dialog title
     * @param string $prompt Input prompt text
     * @param string $defaultValue Default input value
     * @param callable $onSubmit Callback with input value
     * @param callable|null $onCancel Callback for cancellation
     * @param callable|null $validator Optional validation function
     * @return void
     */
    public function showInputDialog(string $title, string $prompt, string $defaultValue, callable $onSubmit, ?callable $onCancel = null, ?callable $validator = null): void
    {
        $inputWindow = Window::create($title, 450, 200, false);
        Window::setMargined($inputWindow, true);

        $mainBox = Box::newVerticalBox();
        Box::setPadded($mainBox, true);

        // Add prompt label
        $promptLabel = Label::create($prompt);
        Box::append($mainBox, $promptLabel, false);

        // Add input entry
        $inputEntry = Entry::create();
        Entry::setText($inputEntry, $defaultValue);
        Box::append($mainBox, $inputEntry, false);

        // Add validation feedback label
        $validationLabel = Label::create('');
        Box::append($mainBox, $validationLabel, false);

        // Add button box
        $buttonBox = Box::newHorizontalBox();
        Box::setPadded($buttonBox, true);

        $okButton = Button::create('Save');
        $cancelButton = Button::create('Cancel');

        // Validation function
        $validateInput = function() use ($inputEntry, $validationLabel, $okButton, $validator) {
            $value = trim(Entry::text($inputEntry));
            
            if (empty($value)) {
                Label::setText($validationLabel, 'Configuration name cannot be empty');
                Control::disable($okButton);
                return false;
            }
            
            // Check for invalid characters
            if (preg_match('/[<>:"|?*\\\\\/]/', $value)) {
                Label::setText($validationLabel, 'Name contains invalid characters');
                Control::disable($okButton);
                return false;
            }
            
            // Custom validation if provided
            if ($validator && !$validator($value)) {
                Label::setText($validationLabel, 'Invalid configuration name');
                Control::disable($okButton);
                return false;
            }
            
            Label::setText($validationLabel, '✓ Valid name');
            Control::enable($okButton);
            return true;
        };

        // Validate on text change
        Entry::onChanged($inputEntry, function($entry) use ($validateInput) {
            $validateInput();
        });

        Button::onClicked($okButton, function($button) use ($inputWindow, $inputEntry, $onSubmit, $validateInput) {
            if ($validateInput()) {
                $value = trim(Entry::text($inputEntry));
                Control::destroy($inputWindow);
                if ($onSubmit) {
                    $onSubmit($value);
                }
            }
        });

        Button::onClicked($cancelButton, function($button) use ($inputWindow, $onCancel) {
            Control::destroy($inputWindow);
            if ($onCancel) {
                $onCancel();
            }
        });

        // Handle Enter key to submit
        Entry::onChanged($inputEntry, function($entry) use ($validateInput, $inputWindow, $onSubmit) {
            // This is a simplified approach - actual Enter key handling would need key event support
        });

        Box::append($buttonBox, $okButton, true);
        Box::append($buttonBox, $cancelButton, true);
        Box::append($mainBox, $buttonBox, false);

        Window::setChild($inputWindow, $mainBox);
        Control::show($inputWindow);
        
        // Initial validation
        $validateInput();
        
        // Focus on the input entry if possible
        if (method_exists('Kingbes\Libui\Entry', 'focus')) {
            Entry::focus($inputEntry);
        }
    }

    /**
     * Show file open dialog
     * 
     * @return string|null Selected file path or null if cancelled
     */
    public function showOpenFileDialog(): ?string
    {
        try {
            $filePath = Window::openFile($this->parentWindow);
            return !empty($filePath) ? $filePath : null;
        } catch (Exception $e) {
            error_log("File dialog error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Show file save dialog
     * 
     * @return string|null Selected file path or null if cancelled
     */
    public function showSaveFileDialog(): ?string
    {
        try {
            $filePath = Window::saveFile($this->parentWindow);
            return !empty($filePath) ? $filePath : null;
        } catch (Exception $e) {
            error_log("File dialog error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Show specialized save configuration dialog with validation
     * 
     * @param callable $onSave Callback with configuration name
     * @param callable|null $onCancel Callback for cancellation
     * @param array $existingNames Array of existing configuration names
     * @return void
     */
    public function showSaveConfigurationDialog(callable $onSave, ?callable $onCancel = null, array $existingNames = []): void
    {
        $defaultName = 'Configuration_' . date('Y-m-d_H-i-s');
        
        $validator = function($name) use ($existingNames) {
            // Check if name already exists
            return !in_array($name, $existingNames);
        };
        
        $this->showInputDialog(
            'Save Configuration',
            'Enter a name for this configuration:',
            $defaultName,
            function($name) use ($onSave, $existingNames) {
                // Check if configuration already exists and show overwrite confirmation
                if (in_array($name, $existingNames)) {
                    $this->showConfirmationDialog(
                        'Overwrite Configuration',
                        "Configuration '{$name}' already exists.\n\nDo you want to overwrite it?",
                        function() use ($onSave, $name) {
                            $onSave($name);
                        },
                        null,
                        'Overwrite',
                        'Cancel'
                    );
                } else {
                    $onSave($name);
                }
            },
            $onCancel
        );
    }

    /**
     * Show delete confirmation dialog with enhanced messaging
     * 
     * @param string $itemName Name of item to delete
     * @param callable $onConfirm Callback for confirmation
     * @param callable|null $onCancel Callback for cancellation
     * @return void
     */
    public function showDeleteConfirmationDialog(string $itemName, callable $onConfirm, ?callable $onCancel = null): void
    {
        $message = "Are you sure you want to delete '{$itemName}'?\n\n" .
                  "This action cannot be undone.";
        
        $this->showConfirmationDialog(
            'Delete Confirmation',
            $message,
            $onConfirm,
            $onCancel,
            'Delete',
            'Cancel'
        );
    }
}