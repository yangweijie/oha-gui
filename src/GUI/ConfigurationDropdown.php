<?php

declare(strict_types=1);

namespace OhaGui\GUI;

use Kingbes\Libui\Base as LibuiBase;
use Kingbes\Libui\Combobox;
use OhaGui\Core\ConfigurationManager;
use OhaGui\Models\TestConfiguration;

/**
 * Configuration dropdown component for OHA GUI Tool
 * Provides dropdown selection for saved configurations
 */
class ConfigurationDropdown extends BaseGUIComponent
{
    private $combobox;
    private ?ConfigurationManager $configManager = null;
    private array $configurations = [];
    private $onSelectionChangedCallback = null;
    private string $placeholderText = "Select Config";

    /**
     * Initialize the configuration dropdown
     */
    public function __construct()
    {
        $this->configManager = new ConfigurationManager();
        $this->loadConfigurations();
    }

    /**
     * Create the dropdown UI control
     * 
     * @return mixed libui combobox control
     */
    public function createDropdown()
    {
        // Create combobox
        $this->combobox = Combobox::create();

        // Populate with configurations
        $this->populateDropdown();

        // Set up selection change callback
        $callback = function() {
            $this->handleSelectionChanged();
        };
        Combobox::onSelected($this->combobox, $callback);

        return $this->combobox;
    }

    /**
     * Load configurations from configuration manager
     */
    private function loadConfigurations(): void
    {
        try {
            $this->configurations = $this->configManager->listConfigurations();
        } catch (\Throwable $e) {
            error_log("Failed to load configurations: " . $e->getMessage());
            $this->configurations = [];
        }
    }

    /**
     * Populate dropdown with available configurations
     */
    private function populateDropdown(): void
    {
        if ($this->combobox === null) {
            return;
        }
        Combobox::clear($this->combobox);

        // Add placeholder
        Combobox::append($this->combobox, $this->placeholderText);

        // Add configurations
        foreach ($this->configurations as $configName) {
            Combobox::append($this->combobox, $configName);
        }

        // Set to placeholder by default
        Combobox::setSelected($this->combobox, 0);
    }

    /**
     * Refresh configurations and update dropdown
     */
    public function refreshConfigurations(): void
    {
        $this->loadConfigurations();
        
        // Store current selection
        $currentSelection = $this->getSelectedConfiguration();
        
        // Recreate combobox to clear items (libui limitation)
        if ($this->combobox !== null) {
            // We need to recreate the combobox since libui doesn't have a clear method
            // This would typically be handled by the parent component
            $this->populateDropdown();
            
            // Restore selection if it still exists
            if ($currentSelection !== null && $currentSelection !== $this->placeholderText) {
                $this->setSelectedConfiguration($currentSelection);
            }
        }
    }

    /**
     * Get the currently selected configuration name
     * 
     * @return string|null
     */
    public function getSelectedConfiguration(): ?string
    {
        if ($this->combobox === null) {
            return null;
        }

        $selectedIndex = Combobox::selected($this->combobox);

        if ($selectedIndex <= 0) {
            return null; // Placeholder or no selection
        }

        // Adjust for placeholder at index 0
        $configIndex = $selectedIndex - 1;
        
        if (isset($this->configurations[$configIndex])) {
            return $this->configurations[$configIndex];
        }

        return null;
    }

    /**
     * Set the selected configuration by name
     * 
     * @param string $configName
     * @return bool true if selection was successful
     */
    public function setSelectedConfiguration(string $configName): bool
    {
        if ($this->combobox === null) {
            return false;
        }

        $configIndex = array_search($configName, $this->configurations);
        if ($configIndex === false) {
            return false;
        }

        // Add 1 to account for placeholder at index 0
        Combobox::setSelected($this->combobox, $configIndex + 1);
        
        return true;
    }

    /**
     * Get configuration data for the selected configuration
     * 
     * @param string|null $configName Optional specific config name, uses current selection if null
     * @return TestConfiguration|null
     */
    public function getSelectedConfigurationData(?string $configName = null): ?TestConfiguration
    {
        if ($configName === null) {
            $configName = $this->getSelectedConfiguration();
        }

        if ($configName === null) {
            return null;
        }

        try {
            return $this->configManager->loadConfiguration($configName);
        } catch (\Throwable $e) {
            error_log("Failed to load configuration '$configName': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Handle selection change event
     */
    private function handleSelectionChanged(): void
    {
        $selectedConfig = $this->getSelectedConfiguration();
        
        if ($selectedConfig !== null && $this->onSelectionChangedCallback !== null) {
            ($this->onSelectionChangedCallback)($selectedConfig);
        }
    }

    /**
     * Set callback for selection change events
     * 
     * @param callable $callback Function that receives the selected configuration name
     */
    public function onSelectionChanged(callable $callback): void
    {
        $this->onSelectionChangedCallback = $callback;
    }

    /**
     * Add a new configuration to the dropdown
     * 
     * @param string $configName
     */
    public function addConfiguration(string $configName): void
    {
        if (!in_array($configName, $this->configurations)) {
            $this->configurations[] = $configName;
            
            // Add to combobox if it exists
            if ($this->combobox !== null) {
                Combobox::append($this->combobox, $configName);
            }
        }
    }

    /**
     * Remove a configuration from the dropdown
     * 
     * @param string $configName
     */
    public function removeConfiguration(string $configName): void
    {
        $index = array_search($configName, $this->configurations);
        if ($index !== false) {
            unset($this->configurations[$index]);
            $this->configurations = array_values($this->configurations); // Reindex
            
            // Refresh dropdown to reflect changes
            $this->refreshConfigurations();
        }
    }

    /**
     * Check if dropdown has any configurations
     * 
     * @return bool
     */
    public function hasConfigurations(): bool
    {
        return !empty($this->configurations);
    }

    /**
     * Get all available configuration names
     * 
     * @return array
     */
    public function getAvailableConfigurations(): array
    {
        return $this->configurations;
    }

    /**
     * Set placeholder text
     * 
     * @param string $text
     */
    public function setPlaceholderText(string $text): void
    {
        $this->placeholderText = $text;
        
        // Refresh to update placeholder
        if ($this->combobox !== null) {
            $this->refreshConfigurations();
        }
    }

    /**
     * Reset selection to placeholder
     */
    public function resetSelection(): void
    {
        if ($this->combobox !== null) {
            Combobox::setSelected($this->combobox, 0); // Select placeholder
        }
    }

    /**
     * Get the combobox control (for parent component management)
     * 
     * @return mixed
     */
    public function getControl()
    {
        return $this->combobox;
    }

    /**
     * Cleanup resources
     */
    public function cleanup(): void
    {
        // Clear references to libui controls
        $this->combobox = null;
        $this->configurations = [];
        $this->onSelectionChangedCallback = null;
        $this->configManager = null;
    }
}