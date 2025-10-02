<?php

declare(strict_types=1);

namespace OhaGui\GUI;

use FFI\CData;
use Kingbes\Libui\Box;
use Kingbes\Libui\Label;
use Kingbes\Libui\Group;
use Kingbes\Libui\Button;
use Kingbes\Libui\Separator;
use OhaGui\Models\TestConfiguration;
use Throwable;

/**
 * Configuration table component for OHA GUI Tool
 * Displays configurations in a table format with action buttons
 */
class ConfigurationTable extends BaseGUIComponent
{
    private $tableGroup;
    private $tableVBox;
    private array $configurationRows = [];
    private $onEditCallback = null;
    private $onDeleteCallback = null;
    private $onSelectCallback = null;

    /**
     * Initialize the configuration table
     */
    public function __construct()
    {
        // Constructor
    }

    /**
     * Create the table UI control
     * 
     * @return CData libui control
     */
    public function createTable(): CData
    {
        // Create table group
        $this->tableGroup = Group::create("Configurations");
        Group::setMargined($this->tableGroup, true);

        // Create scrollable area for table content
        $this->tableVBox = Box::newVerticalBox();
        Box::setPadded($this->tableVBox, true);

        // Create table header
        $this->createTableHeader();

        // Set table content
        Group::setChild($this->tableGroup, $this->tableVBox);

        return $this->tableGroup;
    }

    /**
     * Create table header row
     */
    private function createTableHeader(): void
    {
        // Create header row
        $headerHBox = Box::newHorizontalBox();
        Box::setPadded($headerHBox, true);

        // Name column header
        $nameHeader = Label::create("名称 (Name)");
        Box::append($headerHBox, $nameHeader, true);

        // Summary column header
        $summaryHeader = Label::create("配置概要 (Configuration Summary)");
        Box::append($headerHBox, $summaryHeader, true);

        // Actions column header
        $actionsHeader = Label::create("操作 (Actions)");
        Box::append($headerHBox, $actionsHeader, true);

        // Add header to table
        Box::append($this->tableVBox, $headerHBox, false);

        // Add separator
        $separator = Separator::createHorizontal();
        Box::append($this->tableVBox, $separator, false);
    }

    /**
     * Populate table with configuration data
     * 
     * @param array $configurations Array of configName => TestConfiguration
     */
    public function populateTable(array $configurations): void
    {
        // Clear existing rows
        $this->clearRows();

        // Add new rows
        foreach ($configurations as $configName => $config) {
            $this->addRow($configName, $config);
        }
    }

    /**
     * Add a single configuration row to the table
     * 
     * @param string $configName
     * @param TestConfiguration $config
     */
    public function addRow(string $configName, TestConfiguration $config): void
    {
        // Create row container
        $rowHBox = Box::newHorizontalBox();
        Box::setPadded($rowHBox, true);

        // Name column
        $nameLabel = Label::create($configName);
        Box::append($rowHBox, $nameLabel, true);

        // Summary column
        $summary = $this->getConfigurationSummary($config);
        $summaryLabel = Label::create($summary);
        Box::append($rowHBox, $summaryLabel, true);

        // Actions column
        $actionsHBox = $this->createActionButtons($configName);
        Box::append($rowHBox, $actionsHBox, true);

        // Add row to table
        Box::append($this->tableVBox,  $rowHBox, false);

        // Store row reference
        $this->configurationRows[$configName] = $rowHBox;

        // Add separator
        $separator = Separator::createHorizontal();
        Box::append($this->tableVBox, $separator, false);
    }

    /**
     * Create action buttons for a configuration row
     * 
     * @param string $configName
     * @return CData libui horizontal box with buttons
     */
    private function createActionButtons(string $configName): CData
    {
        $buttonsHBox = Box::newHorizontalBox();
        Box::setPadded($buttonsHBox, true);

        // Edit button
        $editButton = Button::create("编辑");
        $editCallback = function() use ($configName) {
            $this->handleEditClick($configName);
        };
        Button::onClicked($editButton, $editCallback);
        Box::append($buttonsHBox, $editButton, false);

        // Delete button
        $deleteButton = Button::create("删除");
        $deleteCallback = function() use ($configName) {
            $this->handleDeleteClick($configName);
        };
        Button::onClicked($deleteButton, $deleteCallback);
        Box::append($buttonsHBox, $deleteButton, false);

        // Export button
        $exportButton = Button::create("导出");
        $exportCallback = function() use ($configName) {
            $this->handleExportClick($configName);
        };
        Button::onClicked($exportButton, $exportCallback);
        Box::append($buttonsHBox, $exportButton, false);

        // Select button
        $selectButton = Button::create("选择");
        $selectCallback = function() use ($configName) {
            $this->handleSelectClick($configName);
        };
        Button::onClicked($selectButton, $selectCallback);
        Box::append($buttonsHBox, $selectButton, false);

        return $buttonsHBox;
    }

    /**
     * Remove a configuration row from the table
     * 
     * @param string $configName
     */
    public function removeRow(string $configName): void
    {
        if (isset($this->configurationRows[$configName])) {
            // Remove from tracking
            unset($this->configurationRows[$configName]);
        }
    }

    /**
     * Clear all configuration rows
     */
    private function clearRows(): void
    {
        try {

            // Remove all tracked rows
//            foreach ($this->configurationRows as $configName => $row) {
//                if ($row !== null) {
//                    Control::destroy($row);
//                }
//            }

            $this->configurationRows = [];

            // Recreate table structure if needed
            if ($this->tableVBox !== null && $this->tableGroup !== null) {
//                Control::destroy($this->tableVBox);
                $this->tableVBox = Box::newVerticalBox();
                Box::setPadded($this->tableVBox, true);
                $this->createTableHeader();
                Group::setChild($this->tableGroup, $this->tableVBox);
            }
        } catch (Throwable $e) {
            error_log("ConfigurationTable clearRows error: " . $e->getMessage());
            $this->configurationRows = [];
        }
    }

    /**
     * Generate configuration summary text
     * 
     * @param TestConfiguration $config
     * @return string
     */
    public function getConfigurationSummary(TestConfiguration $config): string
    {
        $summary = [];

        // Add method and URL
        $summary[] = $config->method . ' ' . $config->url;

        // Add connection info
        if ($config->concurrentConnections > 1) {
            $summary[] = $config->concurrentConnections . ' connections';
        }

        // Add duration
        $summary[] = $config->duration . 's duration';

        // Add timeout if different from default
        if ($config->timeout !== 30) {
            $summary[] = $config->timeout . 's timeout';
        }

        // Add headers count if any
        if (!empty($config->headers)) {
            $headerCount = count($config->headers);
            $summary[] = $headerCount . ' header' . ($headerCount > 1 ? 's' : '');
        }

        // Add body indicator if present
        if (!empty($config->body)) {
            $summary[] = 'with body';
        }

        return implode(', ', $summary);
    }

    /**
     * Handle edit button click
     * 
     * @param string $configName
     */
    private function handleEditClick(string $configName): void
    {
        if ($this->onEditCallback !== null) {
            ($this->onEditCallback)($configName);
        }
    }

    /**
     * Handle delete button click
     * 
     * @param string $configName
     */
    private function handleDeleteClick(string $configName): void
    {
        if ($this->onDeleteCallback !== null) {
            ($this->onDeleteCallback)($configName);
        }
    }

    /**
     * Handle export button click
     * 
     * @param string $configName
     */
    private function handleExportClick(string $configName): void
    {
        ImportExportDialog::showExport($configName);
    }

    /**
     * Handle select button click
     * 
     * @param string $configName
     */
    private function handleSelectClick(string $configName): void
    {
        if ($this->onSelectCallback !== null) {
            ($this->onSelectCallback)($configName);
        }
    }

    /**
     * Set callback for edit button clicks
     * 
     * @param callable $callback
     */
    public function setOnEditCallback(callable $callback): void
    {
        $this->onEditCallback = $callback;
    }

    /**
     * Set callback for delete button clicks
     * 
     * @param callable $callback
     */
    public function setOnDeleteCallback(callable $callback): void
    {
        $this->onDeleteCallback = $callback;
    }

    /**
     * Set callback for select button clicks
     * 
     * @param callable $callback
     */
    public function setOnSelectCallback(callable $callback): void
    {
        $this->onSelectCallback = $callback;
    }

    /**
     * Get number of configurations in table
     * 
     * @return int
     */
    public function getRowCount(): int
    {
        return count($this->configurationRows);
    }

    /**
     * Check if table is empty
     * 
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->configurationRows);
    }

    /**
     * Get all configuration names in table
     * 
     * @return array
     */
    public function getConfigurationNames(): array
    {
        return array_keys($this->configurationRows);
    }

    /**
     * Get the table control
     * 
     * @return mixed
     */
    public function getControl(): mixed
    {
        return $this->tableGroup;
    }

    /**
     * Cleanup resources
     */
    public function cleanup(): void
    {
        try {
            // Clear all rows first
            $this->clearRows();
            
            // Cleanup main controls
            $this->tableGroup = null;
            $this->tableVBox = null;
            $this->configurationRows = [];
            
            // Clear callbacks
            $this->onEditCallback = null;
            $this->onDeleteCallback = null;
            $this->onSelectCallback = null;
            
        } catch (Throwable $e) {
            error_log("ConfigurationTable cleanup error: " . $e->getMessage());
        }
    }
}