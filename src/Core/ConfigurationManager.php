<?php

namespace OhaGui\Core;

use OhaGui\Models\TestConfiguration;
use OhaGui\Utils\FileManager;
use DateTime;
use Exception;

/**
 * Configuration Manager for handling test configuration CRUD operations
 * Manages saving, loading, listing, and deleting test configurations
 */
class ConfigurationManager
{
    private FileManager $fileManager;
    private ConfigurationValidator $validator;

    public function __construct(?FileManager $fileManager = null, ?ConfigurationValidator $validator = null)
    {
        $this->fileManager = $fileManager ?? new FileManager();
        $this->validator = $validator ?? new ConfigurationValidator();
    }

    /**
     * Save a test configuration to JSON file
     * 
     * @param string $name Configuration name
     * @param TestConfiguration $config Configuration to save
     * @return bool True on success, false on failure
     */
    public function saveConfiguration(string $name, TestConfiguration $config): bool
    {
        try {
            // Validate configuration before saving
            $validationErrors = $config->validate();
            if (!empty($validationErrors)) {
                error_log("ConfigurationManager::saveConfiguration validation failed: " . implode(', ', $validationErrors));
                return false;
            }

            // Update the configuration name and timestamp
            $config->name = $name;
            $config->updatedAt = new DateTime();

            // Convert to array and save
            $data = $config->toArray();
            
            return $this->fileManager->saveConfigFile($name, $data);
        } catch (Exception $e) {
            error_log("ConfigurationManager::saveConfiguration error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Load a test configuration from JSON file
     * 
     * @param string $name Configuration name
     * @return TestConfiguration|null Configuration object or null if not found/invalid
     */
    public function loadConfiguration(string $name): ?TestConfiguration
    {
        try {
            $data = $this->fileManager->loadConfigFile($name);
            
            if ($data === null) {
                return null;
            }

            // Validate the loaded data structure
            if (!$this->validateConfigurationData($data)) {
                error_log("ConfigurationManager::loadConfiguration invalid data structure for: {$name}");
                return null;
            }

            $config = TestConfiguration::fromArray($data);
            
            // Validate the loaded configuration
            $validationErrors = $config->validate();
            if (!empty($validationErrors)) {
                error_log("ConfigurationManager::loadConfiguration validation failed for {$name}: " . implode(', ', $validationErrors));
                return null;
            }

            return $config;
        } catch (Exception $e) {
            error_log("ConfigurationManager::loadConfiguration error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * List all available configurations with metadata
     * 
     * @return array Array of configuration metadata
     */
    public function listConfigurations(): array
    {
        try {
            $files = $this->fileManager->listConfigFiles();
            $configurations = [];

            foreach ($files as $file) {
                // Try to load basic info from each configuration
                $data = $this->fileManager->loadConfigFile($file['name']);
                
                if ($data !== null && $this->validateConfigurationData($data)) {
                    $configurations[] = [
                        'name' => $file['name'],
                        'url' => $data['url'] ?? '',
                        'method' => $data['method'] ?? 'GET',
                        'concurrentConnections' => $data['concurrentConnections'] ?? 10,
                        'duration' => $data['duration'] ?? 10,
                        'timeout' => $data['timeout'] ?? 30,
                        'headers' => $data['headers'] ?? [],
                        'body' => $data['body'] ?? '',
                        'createdAt' => $data['createdAt'] ?? '',
                        'updatedAt' => $data['updatedAt'] ?? '',
                        'fileSize' => $file['size'],
                        'fileModified' => $file['modified'],
                        'isValid' => true
                    ];
                } else {
                    // Include invalid configurations with error flag
                    $configurations[] = [
                        'name' => $file['name'],
                        'url' => '',
                        'method' => '',
                        'concurrentConnections' => 0,
                        'duration' => 0,
                        'timeout' => 0,
                        'headers' => [],
                        'body' => '',
                        'createdAt' => '',
                        'updatedAt' => '',
                        'fileSize' => $file['size'],
                        'fileModified' => $file['modified'],
                        'isValid' => false,
                        'error' => 'Invalid configuration format'
                    ];
                }
            }

            return $configurations;
        } catch (Exception $e) {
            error_log("ConfigurationManager::listConfigurations error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete a configuration
     * 
     * @param string $name Configuration name to delete
     * @return bool True on success, false on failure
     */
    public function deleteConfiguration(string $name): bool
    {
        try {
            return $this->fileManager->deleteConfigFile($name);
        } catch (Exception $e) {
            error_log("ConfigurationManager::deleteConfiguration error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a configuration exists
     * 
     * @param string $name Configuration name
     * @return bool True if exists, false otherwise
     */
    public function configurationExists(string $name): bool
    {
        return $this->fileManager->configFileExists($name);
    }

    /**
     * Duplicate a configuration with a new name
     * 
     * @param string $sourceName Source configuration name
     * @param string $targetName Target configuration name
     * @return bool True on success, false on failure
     */
    public function duplicateConfiguration(string $sourceName, string $targetName): bool
    {
        try {
            $config = $this->loadConfiguration($sourceName);
            
            if ($config === null) {
                return false;
            }

            // Update timestamps for the duplicate
            $config->createdAt = new DateTime();
            $config->updatedAt = new DateTime();

            return $this->saveConfiguration($targetName, $config);
        } catch (Exception $e) {
            error_log("ConfigurationManager::duplicateConfiguration error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Backup a configuration
     * 
     * @param string $name Configuration name to backup
     * @return bool True on success, false on failure
     */
    public function backupConfiguration(string $name): bool
    {
        try {
            return $this->fileManager->backupConfigFile($name);
        } catch (Exception $e) {
            error_log("ConfigurationManager::backupConfiguration error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get configuration file information
     * 
     * @param string $name Configuration name
     * @return array|null File information or null if not found
     */
    public function getConfigurationInfo(string $name): ?array
    {
        return $this->fileManager->getConfigFileInfo($name);
    }

    /**
     * Import configuration from JSON string
     * 
     * @param string $name Configuration name
     * @param string $jsonContent JSON content to import
     * @return bool True on success, false on failure
     */
    public function importConfiguration(string $name, string $jsonContent): bool
    {
        try {
            // Validate JSON format using the validator
            $validationErrors = $this->validator->validateJsonContent($jsonContent);
            if (!empty($validationErrors)) {
                error_log("ConfigurationManager::importConfiguration JSON validation failed: " . implode(', ', $validationErrors));
                return false;
            }

            $data = json_decode($jsonContent, true);
            $config = TestConfiguration::fromArray($data);
            
            return $this->saveConfiguration($name, $config);
        } catch (Exception $e) {
            error_log("ConfigurationManager::importConfiguration error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Export configuration to JSON string
     * 
     * @param string $name Configuration name
     * @return string|null JSON string or null on failure
     */
    public function exportConfiguration(string $name): ?string
    {
        try {
            $config = $this->loadConfiguration($name);
            
            if ($config === null) {
                return null;
            }

            $data = $config->toArray();
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            
            return $json !== false ? $json : null;
        } catch (Exception $e) {
            error_log("ConfigurationManager::exportConfiguration error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the configuration directory path
     * 
     * @return string Configuration directory path
     */
    public function getConfigurationDirectory(): string
    {
        return $this->fileManager->getConfigDirectory();
    }

    /**
     * Validate configuration data structure using the validator
     * 
     * @param array $data Configuration data to validate
     * @return bool True if valid structure, false otherwise
     */
    private function validateConfigurationData(array $data): bool
    {
        $errors = $this->validator->validateConfigurationData($data);
        return empty($errors);
    }

    /**
     * Get detailed validation errors for configuration data
     * 
     * @param array $data Configuration data to validate
     * @return array Array of validation errors
     */
    public function getValidationErrors(array $data): array
    {
        return $this->validator->validateConfigurationData($data);
    }

    /**
     * Validate configuration file and get detailed report
     * 
     * @param string $name Configuration name
     * @return array Validation result with errors and warnings
     */
    public function validateConfigurationFile(string $name): array
    {
        $filePath = $this->fileManager->getConfigFilePath($name);
        return $this->validator->validateConfigurationFile($filePath);
    }

    /**
     * Get the JSON schema for configuration files
     * 
     * @return array JSON schema array
     */
    public function getConfigurationSchema(): array
    {
        return $this->validator->getConfigurationSchema();
    }
}