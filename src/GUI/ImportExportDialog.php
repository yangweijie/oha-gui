<?php

declare(strict_types=1);

namespace OhaGui\GUI;

use Kingbes\Libui\Control;
use Kingbes\Libui\Window;
use Kingbes\Libui\Box;
use Kingbes\Libui\Label;
use Kingbes\Libui\Entry;
use Kingbes\Libui\Button;
use OhaGui\Core\ConfigurationManager;
use OhaGui\Models\TestConfiguration;
use Throwable;

/**
 * Import/Export dialog for OHA GUI Tool
 * Provides dialogs for importing and exporting configurations
 */
class ImportExportDialog extends BaseGUIComponent
{
    private $window;
    private $vbox;
    private $filePathEntry;
    private $browseButton;
    private $importButton;
    private $exportButton;
    private $cancelButton;
    private $statusLabel;
    
    private ?ConfigurationManager $configManager = null;
    private $onImportCallback = null;
    private $onExportCallback = null;

    /**
     * Initialize the import/export dialog
     */
    public function __construct()
    {
        $this->configManager = new ConfigurationManager();
    }

    /**
     * Show import dialog
     * 
     * @param callable|null $onImport Callback for import success (receives config name)
     */
    public function showImportDialog($onImport = null): void
    {
        $this->onImportCallback = $onImport;
        $this->createDialog("导入配置");
        $this->showDialog();
    }

    /**
     * Show export dialog
     * 
     * @param string $configName Configuration name to export
     * @param callable|null $onExport Callback for export success
     */
    public function showExportDialog(string $configName, $onExport = null): void
    {
        $this->onExportCallback = $onExport;
        $this->createDialog("导出配置", $configName);
        $this->showDialog();
    }

    /**
     * Create the dialog
     * 
     * @param string $title Dialog title
     * @param string|null $configName Configuration name for export
     */
    private function createDialog(string $title, ?string $configName = null): void
    {
        if ($this->window !== null) {
            $this->cleanup();
        }

        // Create dialog window
        $this->window = Window::create(
            $title,
            500,  // width
            250,  // height
            0     // no menubar
        );

        Window::setMargined($this->window, true);

        // Create layout
        $this->createLayout($configName);
        $this->setupEventHandlers();
    }

    /**
     * Create dialog layout
     * 
     * @param string|null $configName Configuration name for export
     */
    private function createLayout(?string $configName = null): void
    {
        // Create main vertical box
        $this->vbox = Box::newVerticalBox();
        Box::setPadded($this->vbox, true);

        // Create file path input
        $fileLabel = Label::create("File Path:");
        Box::append($this->vbox, $fileLabel, false);

        // Create horizontal box for file path entry and browse button
        $fileHBox = Box::newHorizontalBox();
        Box::setPadded($fileHBox, true);

        $this->filePathEntry = Entry::create();
        Box::append($fileHBox, $this->filePathEntry, true);

        $this->browseButton = Button::create("Browse...");
        Box::append($fileHBox, $this->browseButton, false);

        Box::append($this->vbox, $fileHBox, false);

        // Add default file path for export
        if ($configName !== null) {
            $defaultPath = $configName . '.json';
            Entry::setText($this->filePathEntry, $defaultPath);
        }

        // Create buttons
        $this->createButtons($configName);

        // Create status display
        $this->statusLabel = Label::create("");
        Box::append($this->vbox, $this->statusLabel, false);

        // Set window content
        Window::setChild($this->window, $this->vbox);

        // Focus on file path entry
    }

    /**
     * Create dialog buttons
     * 
     * @param string|null $configName Configuration name for export
     */
    private function createButtons(?string $configName = null): void
    {
        // Create horizontal box for buttons
        $buttonsHBox = Box::newHorizontalBox();
        Box::setPadded($buttonsHBox, true);

        // Add spacer to center buttons
        $spacer1 = Label::create("");
        Box::append($buttonsHBox, $spacer1, true);

        if ($configName !== null) {
            // Export button
            $this->exportButton = Button::create("Export");
            $exportCallback = function() use ($configName) {
                $this->onExport($configName);
            };
            Button::onClicked($this->exportButton, $exportCallback);
            Box::append($buttonsHBox, $this->exportButton, false);
        } else {
            // Import button
            $this->importButton = Button::create("Import");
            $importCallback = function() {
                $this->onImport();
            };
            Button::onClicked($this->importButton, $importCallback);
            Box::append($buttonsHBox, $this->importButton, false);
        }

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

        // Browse button callback
        $browseCallback = function() {
            $this->onBrowse();
        };
        Button::onClicked($this->browseButton, $browseCallback);
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
     * Get file path from input
     * 
     * @return string
     */
    private function getFilePath(): string
    {
        if ($this->filePathEntry === null) {
            return "";
        }

        $textPtr = Entry::text($this->filePathEntry);
        return trim($textPtr);
    }

    /**
     * Handle browse button click
     */
    private function onBrowse(): void
    {
        // In a full implementation, this would open a file dialog
        // For now, we'll just show a message
        $this->showStatus("File dialog not implemented in this version");
    }

    /**
     * Handle import button click
     */
    private function onImport(): void
    {
        $filePath = $this->getFilePath();
        
        if (empty($filePath)) {
            $this->showError("Please select a file to import");
            return;
        }
        
        try {
            // Check if file exists
            if (!file_exists($filePath)) {
                $this->showError("File not found: " . $filePath);
                return;
            }
            
            // Read file content
            $content = file_get_contents($filePath);
            if ($content === false) {
                $this->showError("Failed to read file: " . $filePath);
                return;
            }
            
            // Parse JSON
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->showError("Invalid JSON in file: " . json_last_error_msg());
                return;
            }
            
            // Generate configuration name from file name
            $configName = pathinfo($filePath, PATHINFO_FILENAME);
            
            // Import configuration
            $success = $this->configManager->importConfiguration($configName, $data);
            
            if ($success) {
                $this->showStatus("Configuration imported successfully as: " . $configName);
                
                // Call import callback if set
                if ($this->onImportCallback !== null) {
                    ($this->onImportCallback)($configName);
                }
            } else {
                $this->showError("Failed to import configuration");
            }
            
        } catch (Throwable $e) {
            $this->showError("Error importing configuration: " . $e->getMessage());
        }
    }

    /**
     * Handle export button click
     * 
     * @param string $configName Configuration name to export
     */
    private function onExport(string $configName): void
    {
        $filePath = $this->getFilePath();
        
        if (empty($filePath)) {
            $this->showError("Please enter a file path for export");
            return;
        }
        
        try {
            // Export configuration
            $data = $this->configManager->exportConfiguration($configName);
            
            if ($data === null) {
                $this->showError("Configuration not found: " . $configName);
                return;
            }
            
            // Convert to JSON
            $json = json_encode($data, JSON_PRETTY_PRINT);
            if ($json === false) {
                $this->showError("Failed to convert configuration to JSON");
                return;
            }
            
            // Write to file
            $result = file_put_contents($filePath, $json);
            
            if ($result !== false) {
                $this->showStatus("Configuration exported successfully to: " . $filePath);
                
                // Call export callback if set
                if ($this->onExportCallback !== null) {
                    ($this->onExportCallback)();
                }
            } else {
                $this->showError("Failed to write file: " . $filePath);
            }
            
        } catch (Throwable $e) {
            $this->showError("Error exporting configuration: " . $e->getMessage());
        }
    }

    /**
     * Handle cancel button click
     */
    private function onCancel(): void
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
        return false; // Don't destroy, we handle cleanup
    }

    /**
     * Show error message
     * 
     * @param string $message
     */
    private function showError(string $message): void
    {
        if ($this->statusLabel !== null) {
            Label::setText($this->statusLabel, "Error: " . $message);
        }
    }

    /**
     * Show status message
     * 
     * @param string $message
     */
    private function showStatus(string $message): void
    {
        if ($this->statusLabel !== null) {
            Label::setText($this->statusLabel, $message);
        }
    }

    /**
     * Static method to show import dialog
     * 
     * @param callable|null $onImport Callback for import success (receives config name)
     */
    public static function showImport(callable $onImport = null): void
    {
        $dialog = new self();
        $dialog->showImportDialog($onImport);
    }

    /**
     * Static method to show export dialog
     * 
     * @param string $configName Configuration name to export
     * @param callable|null $onExport Callback for export success
     */
    public static function showExport(string $configName, callable $onExport = null): void
    {
        $dialog = new self();
        $dialog->showExportDialog($configName, $onExport);
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
            $this->onImportCallback = null;
            $this->onExportCallback = null;

            // Clear config manager reference
            $this->configManager = null;

            // Cleanup window last
            if ($this->window !== null) {
//                Control::destroy($this->window);
                $this->window = null;
            }

        } catch (Throwable $e) {
            error_log("ImportExportDialog cleanup error: " . $e->getMessage());
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