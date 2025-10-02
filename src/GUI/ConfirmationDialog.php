<?php

declare(strict_types=1);

namespace OhaGui\GUI;

use Kingbes\Libui\Control;
use Kingbes\Libui\Window;
use Kingbes\Libui\Box;
use Kingbes\Libui\Label;
use Kingbes\Libui\Button;

/**
 * Confirmation dialog for OHA GUI Tool
 * Provides yes/no confirmation dialogs
 */
class ConfirmationDialog extends BaseGUIComponent
{
    private $window;
    private $vbox;
    private $messageLabel;
    private $yesButton;
    private $noButton;
    private $onConfirmCallback = null;
    private $onCancelCallback = null;

    /**
     * Show confirmation dialog
     * 
     * @param string $title Dialog title
     * @param string $message Confirmation message
     * @param callable|null $onConfirm Callback for yes/confirm button
     * @param callable|null $onCancel Callback for no/cancel button
     */
    public function show(string $title, string $message, $onConfirm = null, $onCancel = null): void
    {
        $this->onConfirmCallback = $onConfirm;
        $this->onCancelCallback = $onCancel;

        $this->createDialog($title, $message);
        $this->showDialog();
    }

    /**
     * Create the confirmation dialog
     * 
     * @param string $title
     * @param string $message
     */
    private function createDialog(string $title, string $message): void
    {
        if ($this->window !== null) {
            $this->cleanup();
        }

        // Create dialog window
        $this->window = Window::create(
            $title,
            400,  // width
            150,  // height
            0     // no menubar
        );

        Window::setMargined($this->window, true);

        // Create layout
        $this->createLayout($message);
        $this->setupEventHandlers();
    }

    /**
     * Create dialog layout
     * 
     * @param string $message
     */
    private function createLayout(string $message): void
    {
        // Create main vertical box
        $this->vbox = Box::newVerticalBox();
        Box::setPadded($this->vbox, true);

        // Create message label
        $this->messageLabel = Label::create($message);
        Box::append($this->vbox, $this->messageLabel, true);

        // Create buttons
        $this->createButtons();

        // Set window content
        Window::setChild($this->window, $this->vbox);
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

        // Yes button
        $this->yesButton = Button::create("Yes");
        $yesCallback = function() {
            $this->onYes();
        };
        Button::onClicked($this->yesButton, $yesCallback);
        Box::append($buttonsHBox, $this->yesButton, false);

        // No button
        $this->noButton = Button::create("No");
        $noCallback = function() {
            $this->onNo();
        };
        Button::onClicked($this->noButton, $noCallback);
        Box::append($buttonsHBox, $this->noButton, false);

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
    }

    /**
     * Show the dialog
     */
    private function showDialog(): void
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
     * Handle Yes button click
     */
    private function onYes(): void
    {
        // Call confirm callback if set
        if ($this->onConfirmCallback !== null) {
            ($this->onConfirmCallback)();
        }

        // Close dialog
        $this->hide();
        $this->cleanup();
    }

    /**
     * Handle No button click
     */
    private function onNo(): void
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
     * Static method to show delete confirmation
     * 
     * @param string $itemName Name of item to delete
     * @param callable $onConfirm Callback for confirmation
     * @param callable|null $onCancel Optional callback for cancellation
     */
    public static function showDeleteConfirmation(string $itemName, callable $onConfirm, $onCancel = null): void
    {
        $dialog = new self();
        $message = "你想删除 '{$itemName}'?\n\n 该操作无法撤销。";
        $dialog->show("确认删除", $message, $onConfirm, $onCancel);
    }

    /**
     * Static method to show generic confirmation
     * 
     * @param string $title Dialog title
     * @param string $message Confirmation message
     * @param callable $onConfirm Callback for confirmation
     * @param callable|null $onCancel Optional callback for cancellation
     */
    public static function showConfirmation(string $title, string $message, callable $onConfirm, $onCancel = null): void
    {
        $dialog = new self();
        $dialog->show($title, $message, $onConfirm, $onCancel);
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
            \OhaGui\Utils\WindowHelper::centerWindow($this->window);
        } catch (\Throwable $e) {
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
            // Clear callbacks
            $this->onConfirmCallback = null;
            $this->onCancelCallback = null;

            // Cleanup window last
            if ($this->window !== null) {
//                Control::destroy($this->window);
                $this->window = null;
            }

        } catch (\Throwable $e) {
            error_log("ConfirmationDialog cleanup error: " . $e->getMessage());
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
