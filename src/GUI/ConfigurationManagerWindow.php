<?php

declare(strict_types=1);

namespace OhaGui\GUI;

use Kingbes\Libui\Control;
use Kingbes\Libui\Window;
use Kingbes\Libui\Box;
use Kingbes\Libui\Label;
use Kingbes\Libui\Button;
use Kingbes\Libui\Separator;
use OhaGui\Core\ConfigurationManager;
use OhaGui\Utils\WindowHelper;
use Throwable;

/**
 * Configuration management popup window for OHA GUI Tool
 * Provides interface for managing saved configurations
 */
class ConfigurationManagerWindow extends BaseGUIComponent
{
    private $window;
    private $vbox;
    private $addButton;
    private ?ConfigurationTable $configTable = null;
    private ?ConfigurationDialog $configDialog = null;
    private ?ConfigurationManager $configManager;
    private $onConfigurationSelectedCallback = null;
    private $onConfigurationChangedCallback = null;

    /**
     * Initialize the configuration manager window
     */
    public function __construct()
    {
        $this->configManager = new ConfigurationManager();
        $this->createWindow();
        $this->createLayout();
        $this->setupEventHandlers();
    }

    /**
     * Create the popup window
     */
    private function createWindow(): void
    {
        // Create window
        $this->window = Window::create(
            "配置管理",
            600,  // width
            400,  // height
            0     // no menubar
        );

        // Set window properties
        Window::setMargined($this->window, true);
    }

    /**
     * Create the window layout
     */
    private function createLayout(): void
    {
        // Create main vertical box
        $this->vbox = Box::newVerticalBox();
        Box::setPadded($this->vbox, true);

        // Create header section with add button
        $this->createHeaderSection();

        // Create configuration table
        $this->configTable = new ConfigurationTable();
        $this->configTable->setOnEditCallback([$this, 'onEditConfiguration']);
        $this->configTable->setOnDeleteCallback([$this, 'onDeleteConfiguration']);
        $this->configTable->setOnSelectCallback([$this, 'onSelectConfiguration']);

        $tableControl = $this->configTable->createTable();
        Box::append($this->vbox, $tableControl, true);

        // Set window content
        Window::setChild($this->window, $this->vbox);

        // Load configurations into table
        $this->refreshTable();
    }

    /**
     * Create header section with add button
     */
    private function createHeaderSection(): void
    {
        // Create horizontal box for header
        $headerHBox = Box::newHorizontalBox();
        Box::setPadded($headerHBox, true);

        // Add "新增" (Add New) button
        $this->addButton = Button::create("新增");
        Box::append($headerHBox, $this->addButton, false);

        // Add "导入" (Import) button
        $importButton = Button::create("导入");
        $importCallback = function() {
            $this->onImportClick();
        };
        Button::onClicked($importButton, $importCallback);
        Box::append($headerHBox, $importButton, false);

        // Add spacer
        $spacer = Label::create("");
        Box::append($headerHBox, $spacer, true);

        // Add header to main layout
        Box::append($this->vbox, $headerHBox, false);

        // Add separator
        $separator = Separator::createHorizontal();
        Box::append($this->vbox, $separator, false);
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

        // Add button callback
        $addCallback = function() {
            $this->onAddNewClick();
        };
        Button::onClicked($this->addButton, $addCallback);
    }

    /**
     * Show the management window
     */
    public function show(): void
    {
        if ($this->window === null) {
            return;
        }
        
        // Refresh table before showing
        $this->refreshTable();
        // Center window
        $this->centerWindow();
        Window::setTitle($this->window, "配置管理");

        Control::show($this->window);

        // Bring to front
    }

    /**
     * Hide the management window
     */
    public function hide(): void
    {
        if ($this->window === null) {
            return;
        }
        Control::hide($this->window);
    }

    /**
     * Handle window closing event
     * 
     * @return bool true to allow closing
     */
    public function onClosing(): bool
    {
        $this->hide();
        return false; // Don't destroy window, just hide it
    }

    /**
     * Handle add new configuration button click
     */
    public function onAddNewClick(): void
    {
        if ($this->configDialog === null) {
            $this->configDialog = new ConfigurationDialog();
            $this->configDialog->setOnSaveCallback([$this, 'onConfigurationSaved']);
        }

        $this->configDialog->showAddDialog();
    }

    /**
     * Handle import configuration button click
     */
    public function onImportClick(): void
    {
        ImportExportDialog::showImport([$this, 'onConfigurationImported']);
    }

    /**
     * Handle configuration imported event
     *
     */
    public function onConfigurationImported(): void
    {
        // Refresh the table to show imported configuration
        $this->refreshTable();
        
        // Notify that configurations have changed
        if ($this->onConfigurationChangedCallback !== null) {
            ($this->onConfigurationChangedCallback)();
        }
    }

    /**
     * Handle edit configuration request
     * 
     * @param string $configName
     */
    public function onEditConfiguration(string $configName): void
    {
        try {
            $config = $this->configManager->loadConfiguration($configName);
            if ($config === null) {
                $this->showError("Configuration not found: " . $configName);
                return;
            }

            if ($this->configDialog === null) {
                $this->configDialog = new ConfigurationDialog();
                $this->configDialog->setOnSaveCallback([$this, 'onConfigurationSaved']);
            }

            $this->configDialog->showEditDialog($config);

        } catch (Throwable $e) {
            $this->showError("Failed to load configuration: " . $e->getMessage());
        }
    }

    /**
     * Handle delete configuration request
     * 
     * @param string $configName
     */
    public function onDeleteConfiguration(string $configName): void
    {
        // Show confirmation dialog
        $this->showDeleteConfirmation($configName);
    }

    /**
     * Handle select configuration request
     * 
     * @param string $configName
     */
    public function onSelectConfiguration(string $configName): void
    {
        // Call callback if set
        if ($this->onConfigurationSelectedCallback !== null) {
            ($this->onConfigurationSelectedCallback)($configName);
        }

        // Hide the management window
        $this->hide();
    }

    /**
     * Handle configuration saved event
     *
     */
    public function onConfigurationSaved(): void
    {
        // Refresh the table to show updated configurations
        $this->refreshTable();
        
        // Notify that configurations have changed
        if ($this->onConfigurationChangedCallback !== null) {
            ($this->onConfigurationChangedCallback)();
        }
    }

    /**
     * Show delete confirmation dialog
     * 
     * @param string $configName
     */
    private function showDeleteConfirmation(string $configName): void
    {
        ConfirmationDialog::showDeleteConfirmation(
            $configName,
            function() use ($configName) {
                $this->performDelete($configName);
            }
        );
    }

    /**
     * Perform the actual configuration deletion
     * 
     * @param string $configName
     */
    private function performDelete(string $configName): void
    {
        try {
            $success = $this->configManager->deleteConfiguration($configName);
            
            if ($success) {
                // Refresh table to remove deleted configuration
                $this->refreshTable();
                
                // Notify that configurations have changed
                if ($this->onConfigurationChangedCallback !== null) {
                    ($this->onConfigurationChangedCallback)();
                }
            } else {
                $this->showError("Failed to delete configuration: " . $configName);
            }

        } catch (Throwable $e) {
            $this->showError("Error deleting configuration: " . $e->getMessage());
        }
    }

    /**
     * Refresh the configuration table
     */
    public function refreshTable(): void
    {
        if ($this->configTable !== null) {
            try {
                $configurations = $this->configManager->listConfigurations();
                $configData = [];

                foreach ($configurations as $configName) {
                    $config = $this->configManager->loadConfiguration($configName);
                    if ($config !== null) {
                        $configData[$configName] = $config;
                    }
                }

                $this->configTable->populateTable($configData);

            } catch (Throwable $e) {
                error_log("Failed to refresh configuration table: " . $e->getMessage());
            }
        }
    }

    /**
     * Show error message
     * 
     * @param string $message
     */
    private function showError(string $message): void
    {
        error_log("Configuration Manager Error: " . $message);
        // In a full implementation, this would show a proper error dialog
    }

    /**
     * Set callback for configuration selection
     * 
     * @param callable $callback
     */
    public function setOnConfigurationSelectedCallback(callable $callback): void
    {
        $this->onConfigurationSelectedCallback = $callback;
    }

    /**
     * Set callback for when configurations are changed (added/deleted/modified)
     * 
     * @param callable $callback
     */
    public function setOnConfigurationChangedCallback(callable $callback): void
    {
        $this->onConfigurationChangedCallback = $callback;
    }

    /**
     * Get the window control
     * 
     * @return mixed
     */
    public function getWindow(): mixed
    {
        return $this->window;
    }

    /**
     * Check if window is visible
     * 
     * @return bool
     */
    public function isVisible(): bool
    {
        if ($this->window === null) {
            return false;
        }
        return Control::visible($this->window);
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
            // Clear callbacks
            $this->onConfigurationSelectedCallback = null;
            $this->onConfigurationChangedCallback = null;

            // Cleanup window
            if ($this->window !== null) {
//                Control::destroy($this->window);
                $this->window = null;
            }

        } catch (Throwable $e) {
            error_log("ConfigurationManagerWindow cleanup error: " . $e->getMessage());
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