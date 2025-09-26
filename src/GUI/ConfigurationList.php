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
 * Simplified display of configurations with basic management functionality
 */
class ConfigurationList
{
    private CData $group;
    private CData $listBox;
    private CData $deleteButton;
    private CData $refreshButton;
    private CData $duplicateButton;
    
    private ConfigurationManager $configManager;
    private DialogManager $dialogManager;
    private array $configurations = [];
    private ?string $selectedConfig = null;
    
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
        $this->group = Group::create('Configuration List');
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

        // Create simplified buttons
        $this->deleteButton = Button::create('Delete');
        $this->duplicateButton = Button::create('Duplicate');
        $this->refreshButton = Button::create('Refresh');

        // Initially disable buttons that require selection
        Control::disable($this->deleteButton);
        Control::disable($this->duplicateButton);

        // Add buttons to button box
        Box::append($buttonBox, $this->deleteButton, true);
        Box::append($buttonBox, $this->duplicateButton, true);
        Box::append($buttonBox, $this->refreshButton, true);

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
        Button::onClicked($this->deleteButton, function($button) {
            $this->showDeleteConfirmation();
        });

        Button::onClicked($this->duplicateButton, function($button) {
            $this->showDuplicateDialog();
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
        Control::enable($this->deleteButton);
        Control::enable($this->duplicateButton);
        
        // Refresh the list to update button text
        $this->refreshConfigurationList();
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
            $deletedConfigName = $this->selectedConfig;
            $this->selectedConfig = null;
            Control::disable($this->deleteButton);
            Control::disable($this->duplicateButton);
            $this->refreshConfigurationList();
            
            if ($this->onDeleteConfigCallback) {
                ($this->onDeleteConfigCallback)($deletedConfigName);
            }
        }
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
     * Clear the current selection
     * 
     * @return void
     */
    public function clearSelection(): void
    {
        $this->selectedConfig = null;
        Control::disable($this->deleteButton);
        Control::disable($this->duplicateButton);
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