<?php

namespace OhaGui\Core;

use DateTime;
use Exception;
use OhaGui\Models\TestConfiguration;
use OhaGui\Utils\FileManager;
use RuntimeException;
use InvalidArgumentException;

/**
 * Configuration Manager class for handling CRUD operations on test configurations
 * Manages saving, loading, listing, and deleting configurations stored as JSON files
 */
class ConfigurationManager
{
    private FileManager $fileManager;
    private ConfigurationValidator $validator;

    /**
     * Constructor
     * 
     * @param FileManager|null $fileManager Optional custom file manager
     * @param ConfigurationValidator|null $validator Optional custom validator
     */
    public function __construct(?FileManager $fileManager = null, ?ConfigurationValidator $validator = null)
    {
        $this->fileManager = $fileManager ?? new FileManager();
        $this->validator = $validator ?? new ConfigurationValidator();
    }

    /**
     * Save a configuration to a JSON file
     * 
     * @param string $name Configuration name (used as filename)
     * @param TestConfiguration $config Configuration to save
     * @return bool True on success
     * @throws RuntimeException If save operation fails
     * @throws InvalidArgumentException If configuration is invalid
     */
    public function saveConfiguration(string $name, TestConfiguration $config): bool
    {
        // Validate configuration name
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Configuration name cannot be empty');
        }

        // Set the name in the configuration object
        $config->name = trim($name);
        
        // Validate the configuration using both model validation and validator
        $modelErrors = $config->validate();
        $validatorErrors = $this->validator->validateConfiguration($config->toArray());
        $allErrors = array_merge($modelErrors, $validatorErrors);
        
        if (!empty($allErrors)) {
            throw new InvalidArgumentException('Configuration validation failed: ' . implode(', ', array_unique($allErrors)));
        }

        // Update the updatedAt timestamp
        $config->touch();

        try {
            // Convert configuration to array for JSON storage
            $configData = $config->toArray();
            
            // Check file system health before attempting save
            $healthCheck = $this->fileManager->checkFileSystemHealth();
            if (!$healthCheck['overall_health']) {
                $issues = implode('; ', $healthCheck['issues']);
                throw new RuntimeException("File system issues detected: {$issues}");
            }
            
            // Create backup if file already exists
            $backupPath = null;
            if ($this->configurationExists($name)) {
                try {
                    $backupPath = $this->backupConfiguration($name);
                } catch (RuntimeException $backupError) {
                    // Log backup failure but continue with save
                    error_log("Warning: Failed to create backup for '{$name}': " . $backupError->getMessage());
                }
            }
            
            // Save to file using FileManager
            $result = $this->fileManager->saveConfigFile($name, $configData);
            
            // Clean up backup if save was successful and backup was created
            if ($result && $backupPath && file_exists($backupPath)) {
                @unlink($backupPath);
            }
            
            return $result;
            
        } catch (RuntimeException $e) {
            // Try to recover from common file system errors
            if (str_contains($e->getMessage(), 'permission') ||
                str_contains($e->getMessage(), 'writable')) {
                
                // Attempt recovery and retry once
                if ($this->fileManager->attemptRecovery($name, 'write')) {
                    try {
                        return $this->fileManager->saveConfigFile($name, $config->toArray());
                    } catch (RuntimeException $retryException) {
                        throw new RuntimeException(
                            "Failed to save configuration '{$name}' even after recovery attempt: " . 
                            $retryException->getMessage()
                        );
                    }
                }
            }
            
            throw new RuntimeException("Failed to save configuration '{$name}': " . $e->getMessage());
        }
    }

    /**
     * Load a configuration from a JSON file
     * 
     * @param string $name Configuration name (filename without extension)
     * @return TestConfiguration|null Configuration object or null if not found
     * @throws RuntimeException If file exists but cannot be loaded or parsed
     * @throws InvalidArgumentException|Exception If loaded configuration is invalid
     */
    public function loadConfiguration(string $name): ?TestConfiguration
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Configuration name cannot be empty');
        }

        try {
            // Load configuration data from file
            $configData = $this->fileManager->loadConfigFile($name);
            
            if ($configData === null) {
                return null; // File doesn't exist
            }

            // Validate configuration file structure first
            $fileErrors = $this->validator->validateConfigurationFile(json_encode($configData));
            if (!empty($fileErrors)) {
                throw new InvalidArgumentException("Configuration file '{$name}' is invalid: " . implode(', ', $fileErrors));
            }

            // Sanitize and create configuration object from array
            $sanitizedData = $this->validator->sanitizeConfiguration($configData);
            $config = TestConfiguration::fromArray($sanitizedData);
            
            // Final validation of the loaded configuration
            $validationErrors = $config->validate();
            if (!empty($validationErrors)) {
                throw new InvalidArgumentException("Loaded configuration '{$name}' is invalid: " . implode(', ', $validationErrors));
            }

            return $config;
            
        } catch (RuntimeException $e) {
            // Try to recover from read permission issues
            if (str_contains($e->getMessage(), 'permission') ||
                str_contains($e->getMessage(), 'readable')) {
                
                // Attempt recovery and retry once
                if ($this->fileManager->attemptRecovery($name, 'read')) {
                    try {
                        $configData = $this->fileManager->loadConfigFile($name);
                        if ($configData !== null) {
                            $sanitizedData = $this->validator->sanitizeConfiguration($configData);
                            return TestConfiguration::fromArray($sanitizedData);
                        }
                    } catch (RuntimeException $retryException) {
                        throw new RuntimeException(
                            "Failed to load configuration '{$name}' even after recovery attempt: " . 
                            $retryException->getMessage()
                        );
                    }
                }
            }
            
            // Check if this is a corrupted file that we can potentially recover
            if (str_contains($e->getMessage(), 'JSON') || str_contains($e->getMessage(), 'parse')) {
                throw new RuntimeException(
                    "Configuration file '{$name}' appears to be corrupted: " . $e->getMessage() . 
                    ". You may need to recreate this configuration or restore from a backup."
                );
            }
            
            throw new RuntimeException("Failed to load configuration '{$name}': " . $e->getMessage());
        }
    }

    /**
     * List all available configurations
     * 
     * @return array Array of configuration names
     */
    public function listConfigurations(): array
    {
        try {
            return $this->fileManager->listConfigFiles();
        } catch (RuntimeException $e) {
            // Log the error for debugging but don't crash the application
            error_log("Warning: Failed to list configuration files: " . $e->getMessage());
            
            // Try to recover from permission issues
            if (str_contains($e->getMessage(), 'permission') ||
                str_contains($e->getMessage(), 'readable')) {
                
                // Attempt recovery and try again
                if ($this->fileManager->attemptRecovery('', 'read')) {
                    try {
                        return $this->fileManager->listConfigFiles();
                    } catch (RuntimeException $retryException) {
                        error_log("Recovery attempt failed: " . $retryException->getMessage());
                    }
                }
            }
            
            // If we can't list files, return empty array rather than throwing
            // This allows the application to continue working even if there are permission issues
            return [];
        }
    }

    /**
     * Delete a configuration file
     * 
     * @param string $name Configuration name (filename without extension)
     * @return bool True on success
     * @throws RuntimeException If deletion fails
     * @throws InvalidArgumentException If name is invalid
     */
    public function deleteConfiguration(string $name): bool
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Configuration name cannot be empty');
        }

        try {
            return $this->fileManager->deleteConfigFile($name);
        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to delete configuration '{$name}': " . $e->getMessage());
        }
    }

    /**
     * Check if a configuration exists
     * 
     * @param string $name Configuration name
     * @return bool True if configuration exists
     */
    public function configurationExists(string $name): bool
    {
        if (empty(trim($name))) {
            return false;
        }

        return $this->fileManager->configFileExists($name);
    }

    /**
     * Get configuration file path
     * 
     * @param string $name Configuration name
     * @return string Full path to configuration file
     */
    public function getConfigurationPath(string $name): string
    {
        return $this->fileManager->getConfigFilePath($name);
    }

    /**
     * Create a backup of a configuration before modifying it
     * 
     * @param string $name Configuration name
     * @return string|null Path to backup file or null if original doesn't exist
     * @throws RuntimeException If backup cannot be created
     */
    public function backupConfiguration(string $name): ?string
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Configuration name cannot be empty');
        }

        try {
            return $this->fileManager->backupConfigFile($name);
        } catch (RuntimeException $e) {
            throw new RuntimeException("Failed to backup configuration '{$name}': " . $e->getMessage());
        }
    }

    /**
     * Get configuration metadata (file info)
     * 
     * @param string $name Configuration name
     * @return array|null Array with metadata or null if file doesn't exist
     */
    public function getConfigurationMetadata(string $name): ?array
    {
        if (empty(trim($name))) {
            return null;
        }

        if (!$this->configurationExists($name)) {
            return null;
        }

        $modTime = $this->fileManager->getConfigFileModificationTime($name);
        $size = $this->fileManager->getConfigFileSize($name);

        return [
            'name' => $name,
            'path' => $this->getConfigurationPath($name),
            'modified_time' => $modTime,
            'size' => $size,
            'exists' => true
        ];
    }

    /**
     * Validate a configuration file without loading it completely
     * 
     * @param string $name Configuration name
     * @return bool True if file exists and contains valid JSON
     */
    public function validateConfigurationFile(string $name): bool
    {
        if (empty(trim($name))) {
            return false;
        }

        return $this->fileManager->validateConfigFile($name);
    }

    /**
     * Get a summary of all configurations with basic info
     *
     * @return array Array of configuration summaries
     * @throws Exception
     */
    public function getConfigurationSummaries(): array
    {
        $configurations = $this->listConfigurations();
        $summaries = [];

        foreach ($configurations as $name) {
            try {
                $config = $this->loadConfiguration($name);
                if ($config !== null) {
                    $summaries[] = [
                        'name' => $name,
                        'url' => $config->url,
                        'method' => $config->method,
                        'connections' => $config->concurrentConnections,
                        'duration' => $config->duration,
                        'created_at' => $config->createdAt->format('Y-m-d H:i:s'),
                        'updated_at' => $config->updatedAt->format('Y-m-d H:i:s'),
                        'summary' => $this->generateConfigurationSummary($config)
                    ];
                }
            } catch (RuntimeException | InvalidArgumentException $e) {
                // Skip invalid configurations but include them in the list with error info
                $summaries[] = [
                    'name' => $name,
                    'error' => 'Invalid configuration: ' . $e->getMessage(),
                    'summary' => 'Error loading configuration'
                ];
            }
        }

        return $summaries;
    }

    /**
     * Generate a human-readable summary of a configuration
     * 
     * @param TestConfiguration $config
     * @return string Configuration summary
     */
    public function generateConfigurationSummary(TestConfiguration $config): string
    {
        $url = strlen($config->url) > 50 ? substr($config->url, 0, 47) . '...' : $config->url;
        
        return sprintf(
            '%s %s (%d conn, %ds)',
            strtoupper($config->method),
            $url,
            $config->concurrentConnections,
            $config->duration
        );
    }

    /**
     * Import configuration from array data (useful for importing from other sources)
     * 
     * @param string $name Configuration name
     * @param array $data Configuration data array
     * @return bool True on success
     * @throws RuntimeException If import fails
     * @throws InvalidArgumentException|Exception If data is invalid
     */
    public function importConfiguration(string $name, array $data): bool
    {
        try {
            $config = TestConfiguration::fromArray($data);
            return $this->saveConfiguration($name, $config);
        } catch (RuntimeException | InvalidArgumentException $e) {
            throw new RuntimeException("Failed to import configuration '{$name}': " . $e->getMessage());
        }
    }

    /**
     * Export configuration to array format
     * 
     * @param string $name Configuration name
     * @return array|null Configuration data array or null if not found
     * @throws RuntimeException|Exception If export fails
     */
    public function exportConfiguration(string $name): ?array
    {
        $config = $this->loadConfiguration($name);
        return $config?->toArray();
    }

    /**
     * Duplicate a configuration with a new name
     * 
     * @param string $sourceName Source configuration name
     * @param string $targetName Target configuration name
     * @return bool True on success
     * @throws RuntimeException If duplication fails
     * @throws InvalidArgumentException|Exception If source doesn't exist or target name is invalid
     */
    public function duplicateConfiguration(string $sourceName, string $targetName): bool
    {
        $sourceConfig = $this->loadConfiguration($sourceName);
        
        if ($sourceConfig === null) {
            throw new InvalidArgumentException("Source configuration '{$sourceName}' does not exist");
        }

        // Create a copy with updated timestamps
        $targetConfig = TestConfiguration::fromArray($sourceConfig->toArray());
        $targetConfig->name = $targetName;
        $targetConfig->createdAt = new DateTime();
        $targetConfig->updatedAt = new DateTime();

        return $this->saveConfiguration($targetName, $targetConfig);
    }

    /**
     * Get storage information
     * 
     * @return array Storage statistics
     */
    public function getStorageInfo(): array
    {
        $diskInfo = $this->fileManager->getDiskSpaceInfo();
        $configurations = $this->listConfigurations();
        
        $totalSize = 0;
        foreach ($configurations as $name) {
            $size = $this->fileManager->getConfigFileSize($name);
            if ($size !== null) {
                $totalSize += $size;
            }
        }

        return [
            'configuration_count' => count($configurations),
            'total_size' => $totalSize,
            'config_directory' => $this->fileManager->getConfigDirectory(),
            'disk_free' => $diskInfo['free'],
            'disk_total' => $diskInfo['total'],
            'disk_used' => $diskInfo['used']
        ];
    }

    /**
     * Get comprehensive file system health information
     *
     * @return array Health check results with recommendations
     * @throws Exception
     */
    public function getFileSystemHealth(): array
    {
        $health = $this->fileManager->checkFileSystemHealth();
        $storageInfo = $this->getStorageInfo();
        
        // Add configuration-specific health checks
        $configurations = $this->listConfigurations();
        $corruptedConfigs = [];
        $validConfigs = 0;
        
        foreach ($configurations as $name) {
            try {
                $config = $this->loadConfiguration($name);
                if ($config !== null) {
                    $validConfigs++;
                }
            } catch (RuntimeException | InvalidArgumentException $e) {
                $corruptedConfigs[] = [
                    'name' => $name,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $health['configuration_health'] = [
            'total_configurations' => count($configurations),
            'valid_configurations' => $validConfigs,
            'corrupted_configurations' => count($corruptedConfigs),
            'corrupted_details' => $corruptedConfigs
        ];
        
        // Add recommendations
        $recommendations = [];
        
        if (!$health['disk_space_available']) {
            $recommendations[] = 'Free up disk space to ensure configurations can be saved';
        }
        
        if (!$health['permissions_ok']) {
            $recommendations[] = 'Fix directory permissions to allow reading and writing configuration files';
        }
        
        if (!empty($corruptedConfigs)) {
            $recommendations[] = 'Recreate or restore corrupted configuration files from backups';
        }
        
        if ($storageInfo['disk_free'] < 10 * 1024 * 1024) { // Less than 10MB
            $recommendations[] = 'Consider cleaning up old configuration files or moving to a location with more space';
        }
        
        $health['recommendations'] = $recommendations;
        $health['storage_info'] = $storageInfo;
        
        return $health;
    }
}