<?php

declare(strict_types=1);

namespace OhaGui\GUI;

use Kingbes\Libui\Box;
use Kingbes\Libui\Label;
use Kingbes\Libui\Group;
use Kingbes\Libui\MultilineEntry;
use OhaGui\Models\TestConfiguration;

/**
 * Request overview component for OHA GUI Tool
 * Displays a formatted overview of the request configuration
 */
class RequestOverview extends BaseGUIComponent
{
    private $overviewGroup;
    private $overviewEntry;

    /**
     * Initialize the request overview
     */
    public function __construct()
    {
        // Constructor
    }

    /**
     * Create the request overview UI
     * 
     * @return mixed libui control
     */
    public function createOverview()
    {
        // Create main overview group
        $this->overviewGroup = Group::create("输入 (Input)");
        Group::setMargined($this->overviewGroup, false); // Remove margins

        // Create overview layout
        $overviewBox = Box::newVerticalBox();
        Box::setPadded($overviewBox, false); // Remove padding

        // Create overview text area with controlled height
        $this->overviewEntry = MultilineEntry::create();
        MultilineEntry::setReadOnly($this->overviewEntry, true);
        MultilineEntry::setText($this->overviewEntry, "请选择一个配置以查看请求详情\n配置:\nURL: \nMethod: \nConnections: \nDuration: \nTimeout: \nHeaders: \nBody: ");
        
        // Set the text area with fixed height by not allowing it to stretch
        Box::append($overviewBox, $this->overviewEntry, false);
        
        // Minimize the space used by this component by not adding extra elements

        // Set overview content
        Group::setChild($this->overviewGroup, $overviewBox);

        return $this->overviewGroup;
    }

    /**
     * Update the overview text with configuration details
     * 
     * @param TestConfiguration $config
     */
    public function updateOverview(TestConfiguration $config): void
    {
        $overviewText = "请求概况:\n";
        $overviewText .= "配置: " . ($config->name ?? '未命名') . "\n";
        $overviewText .= "URL: " . $config->url . "\n";
        $overviewText .= "Method: " . $config->method . " | Connections: " . $config->concurrentConnections . "\n";
        $overviewText .= "Duration: " . $config->duration . "s | Timeout: " . $config->timeout . "s\n";
        
        // Format headers
        $headersText = "";
        if (!empty($config->headers)) {
            foreach ($config->headers as $name => $value) {
                $headersText .= "$name: $value\n";
            }
        }
        $overviewText .= "Headers: " . ($headersText ?: 'None') . "\n";
        
        // Add body if present
        $overviewText .= "Body: " . ($config->body ?: 'None') . "\n";
        
        MultilineEntry::setText($this->overviewEntry, $overviewText);
    }

    /**
     * Set default overview text
     */
    public function setDefaultOverview(): void
    {
        MultilineEntry::setText($this->overviewEntry, "请选择一个配置以查看请求详情\n配置:\nURL: \nMethod: | Connections: \nDuration: s | Timeout: s\nHeaders: \nBody: ");
    }

    /**
     * Cleanup resources
     */
    public function cleanup(): void
    {
        try {
            // Clear references to libui controls
            $this->overviewGroup = null;
            $this->overviewEntry = null;
            
        } catch (\Throwable $e) {
            error_log("RequestOverview cleanup error: " . $e->getMessage());
        }
    }
}
