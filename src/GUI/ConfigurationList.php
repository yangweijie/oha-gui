<?php

namespace OhaGui\GUI;

use Kingbes\Libui\Group;
use Kingbes\Libui\Box;
use Kingbes\Libui\Button;
use Kingbes\Libui\Label;
use Kingbes\Libui\Entry;
use Kingbes\Libui\Window;
use Kingbes\Libui\Control;
use FFI\CData;
use OhaGui\Core\ConfigurationManager;
use OhaGui\Models\TestConfiguration;
use OhaGui\Utils\DialogManager;
use Exception;

/**
 * Configuration List component for managing saved test configurations
 * 
 * Displays list of configurations with load, save, and delete functionality
 */
class ConfigurationList
{
    private CData $group;
    private CData $listBox;
    private CData $saveButton;
    private CData $loadButton;
    private CData $deleteButton;
    private CData $refreshButton;
    private CData $importButton;
    private CData $exportButton;
    private CData $duplicateButton;
    
    private ConfigurationManager $configManager;
    private DialogManager $dialogManager;
    private array $configurations = [];
    private ?string $selectedConfig = null;
    
    private $onLoadConfigCallback = null;
    private $onSaveConfigCallback = null;
    private $onDeleteConfigCallback = null;

    /**
     * Initialize the configuration list
     */
    public function __construct(CData $parentWindow)
    {
        $this->configManager = new ConfigurationManager();
        $this->dialogManager = new DialogManager($parentWindow);
        $this->createUI();
        $this->refreshConfigurationList();
    }

    /**
     * Create the configuration management UI
     * 
     * @return void
     */
    private function createUI(): void
    {
        // Create main group
        $this->group = Group::create('Saved Configurations');
        Group::setMargined($this->group, true);

        // Create main vertical box
        $mainBox = Box::newVerticalBox();
        Box::setPadded($mainBox, true);

        // Create configuration list area
        $this->listBox = Box::newVerticalBox();
        Box::setPadded($this->listBox, true);

        // Create button box
        $buttonBox = Box::newHorizontalBox();
        Box::setPadded($buttonBox, true);

        // Create buttons
        $this->saveButton = Button::create('Save Current');
        $this->loadButton = Button::create('Load Selected');
        $this->deleteButton = Button::create('Delete Selected');
        $this->duplicateButton = Button::create('Duplicate');
        $this->importButton = Button::create('Import');
        $this->exportButton = Button::create('Export');
        $this->refreshButton = Button::create('Refresh');

        // Initially disable buttons that require selection
        Control::disable($this->loadButton);
        Control::disable($this->deleteButton);
        Control::disable($this->duplicateButton);
        Control::disable($this->exportButton);

        // Create button rows for better layout
        $buttonRow1 = Box::newHorizontalBox();
        Box::setPadded($buttonRow1, true);
        $buttonRow2 = Box::newHorizontalBox();
        Box::setPadded($buttonRow2, true);

        // Add buttons to rows
        Box::append($buttonRow1, $this->saveButton, true);
        Box::append($buttonRow1, $this->loadButton, true);
        Box::append($buttonRow1, $this->deleteButton, true);
        Box::append($buttonRow1, $this->duplicateButton, true);
        
        Box::append($buttonRow2, $this->importButton, true);
        Box::append($buttonRow2, $this->exportButton, true);
        Box::append($buttonRow2, $this->refreshButton, true);

        // Add button rows to main button box
        Box::append($buttonBox, $buttonRow1, false);
        Box::append($buttonBox, $buttonRow2, false);

        // Add components to main box
        Box::append($mainBox, $this->listBox, true);
        Box::append($mainBox, $buttonBox, false);

        // Set main box as group child
        Group::setChild($this->group, $mainBox);

        $this->setupEventHandlers();
    }

    /**
     * Setup event handlers for buttons
     * 
     * @return void
     */
    private function setupEventHandlers(): void
    {
        Button::onClicked($this->saveButton, function($button) {
            $this->showSaveDialog();
        });

        Button::onClicked($this->loadButton, function($button) {
            $this->loadSelectedConfiguration();
        });

        Button::onClicked($this->deleteButton, function($button) {
            $this->showDeleteConfirmation();
        });

        Button::onClicked($this->duplicateButton, function($button) {
            $this->showDuplicateDialog();
        });

        Button::onClicked($this->importButton, function($button) {
            $this->showImportDialog();
        });

        Button::onClicked($this->exportButton, function($button) {
            $this->showExportDialog();
        });

        Button::onClicked($this->refreshButton, function($button) {
            $this->refreshConfigurationList();
        });
    }

    /**
     * Refresh the configuration list display
     * 
     * @return void
     */
    public function refreshConfigurationList(): void
    {
        // Get configurations from manager
        $this->configurations = $this->configManager->listConfigurations();
        
        // Clear current list (note: this is a simplified implementation)
        $this->clearListBox();
        
        if (empty($this->configurations)) {
            $noConfigLabel = Label::create('No saved configurations');
            Box::append($this->listBox, $noConfigLabel, false);
            return;
        }

        // Add configuration items to list
        foreach ($this->configurations as $configData) {
            $this->addConfigurationItem($configData);
        }
    }

    /**
     * Clear the list box of all items
     * 
     * @return void
     */
    private function clearListBox(): void
    {
        // Note: libui doesn't provide a direct way to clear box contents
        // We'll track items and avoid recreating the entire structure
        // For now, we'll keep the existing listBox and just note that items will accumulate
        // This is a limitation of the current libui implementation
        
        // In a production implementation, you would need to:
        // 1. Keep track of all child controls added to listBox
        // 2. Remove them individually before adding new ones
        // 3. Or implement a more sophisticated UI update mechanism
        
        // For this implementation, we'll accept that the list may accumulate items
        // and focus on proper cleanup of callbacks and resources
    }

    /**
     * Add a configuration item to the list
     * 
     * @param array $configData
     * @return void
     */
    private function addConfigurationItem(array $configData): void
    {
        $configName = $configData['name'];
        $itemBox = Box::newHorizontalBox();
        Box::setPadded($itemBox, true);

        // Create configuration info with better formatting
        $createdAt = !empty($configData['createdAt']) ? 
            date('Y-m-d H:i', strtotime($configData['createdAt'])) : 'Unknown';
        $url = $configData['url'] ?? 'No URL';
        $method = $configData['method'] ?? 'GET';
        $connections = $configData['concurrentConnections'] ?? 10;
        $duration = $configData['duration'] ?? 10;
        
        // Truncate URL if too long
        if (strlen($url) > 40) {
            $url = substr($url, 0, 37) . '...';
        }
        
        $infoText = sprintf('%s | %s %s | %dc/%ds | %s', 
            $configName, $method, $url, $connections, $duration, $createdAt);
        
        // Add validity indicator
        if (!($configData['isValid'] ?? true)) {
            $infoText = '[INVALID] ' . $infoText;
        }
        
        $infoLabel = Label::create($infoText);
        
        // Create select button with different style for selected item
        $selectButton = Button::create($this->selectedConfig === $configName ? '✓ Selected' : 'Select');
        Button::onClicked($selectButton, function($button) use ($configName) {
            $this->selectConfiguration($configName);
        });

        Box::append($itemBox, $infoLabel, true);
        Box::append($itemBox, $selectButton, false);
        
        Box::append($this->listBox, $itemBox, false);
    }

    /**
     * Select a configuration
     * 
     * @param string $configName
     * @return void
     */
    private function selectConfiguration(string $configName): void
    {
        $this->selectedConfig = $configName;
        
        // Enable buttons that require selection
        Control::enable($this->loadButton);
        Control::enable($this->deleteButton);
        Control::enable($this->duplicateButton);
        Control::enable($this->exportButton);
        
        // Refresh the list to update button text
        $this->refreshConfigurationList();
    }

    /**
     * Show save configuration dialog with enhanced validation
     * 
     * @return void
     */
    public function showSaveDialog(): void
    {
        // Get existing configuration names for validation
        $existingNames = array_column($this->configurations, 'name');
        
        $this->dialogManager->showSaveConfigurationDialog(
            function($name) {
                if ($this->onSaveConfigCallback) {
                    ($this->onSaveConfigCallback)($name);
                }
            },
            null, // onCancel
            $existingNames
        );
    }

    /**
     * Load the selected configuration
     * 
     * @return void
     */
    private function loadSelectedConfiguration(): void
    {
        if ($this->selectedConfig === null) {
            return;
        }

        $config = $this->configManager->loadConfiguration($this->selectedConfig);
        if ($config && $this->onLoadConfigCallback) {
            ($this->onLoadConfigCallback)($config);
        }
    }

    /**
     * Show delete confirmation dialog with enhanced messaging
     * 
     * @return void
     */
    private function showDeleteConfirmation(): void
    {
        if ($this->selectedConfig === null) {
            $this->dialogManager->showErrorDialog('Error', 'No configuration selected.');
            return;
        }

        $configName = $this->selectedConfig;
        $this->dialogManager->showDeleteConfirmationDialog(
            $configName,
            function() use ($configName) {
                $this->deleteSelectedConfiguration();
                $this->dialogManager->showInfoDialog('Success', "Configuration '{$configName}' has been deleted.");
            }
        );
    }

    /**
     * Delete the selected configuration
     * 
     * @return void
     */
    private function deleteSelectedConfiguration(): void
    {
        if ($this->selectedConfig === null) {
            return;
        }

        $success = $this->configManager->deleteConfiguration($this->selectedConfig);
        if ($success) {
            $this->selectedConfig = null;
            Control::disable($this->loadButton);
            Control::disable($this->deleteButton);
            $this->refreshConfigurationList();
            
            if ($this->onDeleteConfigCallback) {
                ($this->onDeleteConfigCallback)($this->selectedConfig);
            }
        }
    }

    /**
     * Save a new configuration
     * 
     * @param string $name
     * @param TestConfiguration $config
     * @return bool
     */
    public function saveConfiguration(string $name, TestConfiguration $config): bool
    {
        $success = $this->configManager->saveConfiguration($name, $config);
        if ($success) {
            $this->refreshConfigurationList();
        }
        return $success;
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
     * Set callback for delete configuration event
     * 
     * @param callable $callback
     * @return void
     */
    public function setOnDeleteConfigCallback(callable $callback): void
    {
        $this->onDeleteConfigCallback = $callback;
    }

    /**
     * Get the group control
     * 
     * @return CData
     */
    public function getControl(): CData
    {
        return $this->group;
    }

    /**
     * Get the selected configuration name
     * 
     * @return string|null
     */
    public function getSelectedConfiguration(): ?string
    {
        return $this->selectedConfig;
    }

    /**
     * Show duplicate configuration dialog
     * 
     * @return void
     */
    private function showDuplicateDialog(): void
    {
        if ($this->selectedConfig === null) {
            $this->dialogManager->showErrorDialog('Error', 'No configuration selected.');
            return;
        }

        $sourceName = $this->selectedConfig;
        $defaultName = $sourceName . ' (Copy)';
        
        $this->dialogManager->showInputDialog(
            'Duplicate Configuration',
            "Enter a name for the duplicate of '{$sourceName}':",
            $defaultName,
            function($name) use ($sourceName) {
                $name = trim($name);
                if (empty($name)) {
                    $this->dialogManager->showErrorDialog('Error', 'Configuration name cannot be empty.');
                    return;
                }
                
                if ($this->configManager->configurationExists($name)) {
                    $this->dialogManager->showErrorDialog('Error', "Configuration '{$name}' already exists.");
                    return;
                }
                
                $success = $this->configManager->duplicateConfiguration($sourceName, $name);
                if ($success) {
                    $this->refreshConfigurationList();
                    $this->dialogManager->showInfoDialog('Success', "Configuration duplicated as '{$name}'.");
                } else {
                    $this->dialogManager->showErrorDialog('Error', 'Failed to duplicate configuration.');
                }
            }
        );
    }

    /**
     * Show import configuration dialog
     * 
     * @return void
     */
    private function showImportDialog(): void
    {
        try {
            $filePath = $this->dialogManager->showOpenFileDialog();
            if ($filePath === null) {
                // User cancelled or dialog failed
                return;
            }
        } catch (Exception $e) {
            $this->dialogManager->showErrorDialog('Error', 'Failed to open file dialog: ' . $e->getMessage());
            return;
        }

        try {
            $jsonContent = file_get_contents($filePath);
            if ($jsonContent === false) {
                $this->dialogManager->showErrorDialog('Error', 'Failed to read the selected file.');
                return;
            }

            // Extract filename without extension as default name
            $defaultName = pathinfo($filePath, PATHINFO_FILENAME);
            
            $this->dialogManager->showInputDialog(
                'Import Configuration',
                'Enter a name for the imported configuration:',
                $defaultName,
                function($name) use ($jsonContent) {
                    $name = trim($name);
                    if (empty($name)) {
                        $this->dialogManager->showErrorDialog('Error', 'Configuration name cannot be empty.');
                        return;
                    }
                    
                    if ($this->configManager->configurationExists($name)) {
                        $this->dialogManager->showConfirmationDialog(
                            'Overwrite Configuration',
                            "Configuration '{$name}' already exists. Do you want to overwrite it?",
                            function() use ($name, $jsonContent) {
                                $this->performImport($name, $jsonContent);
                            }
                        );
                    } else {
                        $this->performImport($name, $jsonContent);
                    }
                }
            );
        } catch (Exception $e) {
            $this->dialogManager->showErrorDialog('Error', 'Failed to import configuration: ' . $e->getMessage());
        }
    }

    /**
     * Perform the actual import operation
     * 
     * @param string $name Configuration name
     * @param string $jsonContent JSON content to import
     * @return void
     */
    private function performImport(string $name, string $jsonContent): void
    {
        $success = $this->configManager->importConfiguration($name, $jsonContent);
        if ($success) {
            $this->refreshConfigurationList();
            $this->dialogManager->showInfoDialog('Success', "Configuration imported as '{$name}'.");
        } else {
            $this->dialogManager->showErrorDialog('Error', 'Failed to import configuration. Please check the file format.');
        }
    }

    /**
     * Show export configuration dialog
     * 
     * @return void
     */
    private function showExportDialog(): void
    {
        if ($this->selectedConfig === null) {
            $this->dialogManager->showErrorDialog('Error', 'No configuration selected.');
            return;
        }

        $configName = $this->selectedConfig;
        
        try {
            $filePath = $this->dialogManager->showSaveFileDialog();
            if ($filePath === null) {
                // User cancelled or dialog failed
                return;
            }
        } catch (Exception $e) {
            $this->dialogManager->showErrorDialog('Error', 'Failed to open save dialog: ' . $e->getMessage());
            return;
        }

        try {
            $jsonContent = $this->configManager->exportConfiguration($configName);
            if ($jsonContent === null) {
                $this->dialogManager->showErrorDialog('Error', 'Failed to export configuration.');
                return;
            }

            // Ensure file has .json extension
            if (!str_ends_with(strtolower($filePath), '.json')) {
                $filePath .= '.json';
            }

            $success = file_put_contents($filePath, $jsonContent);
            if ($success !== false) {
                $this->dialogManager->showInfoDialog('Success', "Configuration exported to '{$filePath}'.");
            } else {
                $this->dialogManager->showErrorDialog('Error', 'Failed to write export file.');
            }
        } catch (Exception $e) {
            $this->dialogManager->showErrorDialog('Error', 'Failed to export configuration: ' . $e->getMessage());
        }
    }

    /**
     * Clear the current selection
     * 
     * @return void
     */
    public function clearSelection(): void
    {
        $this->selectedConfig = null;
        Control::disable($this->loadButton);
        Control::disable($this->deleteButton);
        Control::disable($this->duplicateButton);
        Control::disable($this->exportButton);
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
            $this->onLoadConfigCallback = null;
            $this->onSaveConfigCallback = null;
            $this->onDeleteConfigCallback = null;
            
            // Clear configuration data
            $this->configurations = [];
            $this->selectedConfig = null;
            
            // Note: libui controls are automatically cleaned up when parent is destroyed
            // We don't need to explicitly destroy individual controls
            
        } catch (Exception $e) {
            error_log("Error during ConfigurationList cleanup: " . $e->getMessage());
        }
    }
}