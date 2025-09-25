<?php

namespace OhaGui\Utils;

use Exception;
use OhaGui\Core\FileSystemErrorHandler;

/**
 * File manager class for configuration file operations
 */
class FileManager
{
    private string $configDirectory;
    private FileSystemErrorHandler $errorHandler;

    public function __construct(?string $configDirectory = null)
    {
        $this->configDirectory = $configDirectory ?? CrossPlatform::getConfigDirectory();
        $this->errorHandler = new FileSystemErrorHandler();
        $this->ensureConfigDirectoryExists();
    }

    /**
     * Get the configuration directory path
     */
    public function getConfigDirectory(): string
    {
        return $this->configDirectory;
    }

    /**
     * Ensure the configuration directory exists with enhanced error handling
     */
    private function ensureConfigDirectoryExists(): void
    {
        if (!is_dir($this->configDirectory)) {
            $result = $this->errorHandler->createDirectorySafely($this->configDirectory, 0755);
            
            if (!$result['success']) {
                $errorMessages = [];
                foreach ($result['errors'] as $error) {
                    $errorMessages[] = $error['message'];
                }
                throw new Exception("Failed to create configuration directory: " . implode(', ', $errorMessages));
            }
        } else {
            // Validate existing directory access
            $validation = $this->errorHandler->validateDirectoryAccess($this->configDirectory);
            if (!$validation['accessible']) {
                $errorMessages = [];
                foreach ($validation['errors'] as $error) {
                    $errorMessages[] = $error['message'];
                }
                throw new Exception("Configuration directory is not accessible: " . implode(', ', $errorMessages));
            }
        }
    }

    /**
     * Get the full path for a configuration file
     */
    public function getConfigFilePath(string $name): string
    {
        $safeName = $this->sanitizeFileName($name);
        return CrossPlatform::joinPaths($this->configDirectory, $safeName . '.json');
    }

    /**
     * Sanitize a filename to prevent directory traversal and invalid characters
     */
    private function sanitizeFileName(string $name): string
    {
        // Remove or replace invalid characters
        $name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name);
        
        // Remove leading/trailing dots and spaces
        $name = trim($name, '. ');
        
        // Ensure the name is not empty
        if (empty($name)) {
            $name = 'unnamed_config';
        }
        
        // Limit length
        if (strlen($name) > 100) {
            $name = substr($name, 0, 100);
        }
        
        return $name;
    }

    /**
     * Save data to a configuration file with enhanced error handling
     */
    public function saveConfigFile(string $name, array $data): bool
    {
        try {
            $filePath = $this->getConfigFilePath($name);
            $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            
            if ($jsonData === false) {
                error_log("FileManager::saveConfigFile JSON encoding failed for: {$name}");
                return false;
            }
            
            // Use enhanced file writing with error handling
            $result = $this->errorHandler->writeFileSafely($filePath, $jsonData, true);
            
            if (!$result['success']) {
                foreach ($result['errors'] as $error) {
                    error_log("FileManager::saveConfigFile error: " . $error['message']);
                }
                return false;
            }
            
            // Log warnings if any
            foreach ($result['warnings'] as $warning) {
                error_log("FileManager::saveConfigFile warning: " . $warning);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("FileManager::saveConfigFile exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Load data from a configuration file with enhanced error handling
     */
    public function loadConfigFile(string $name): ?array
    {
        try {
            $filePath = $this->getConfigFilePath($name);
            
            // Use enhanced file reading with error handling
            $result = $this->errorHandler->readFileSafely($filePath);
            
            if (!$result['success']) {
                foreach ($result['errors'] as $error) {
                    error_log("FileManager::loadConfigFile error: " . $error['message']);
                }
                return null;
            }
            
            // Log warnings if any
            foreach ($result['warnings'] as $warning) {
                error_log("FileManager::loadConfigFile warning: " . $warning);
            }
            
            // Validate JSON content
            $jsonValidation = $this->errorHandler->validateJsonContent($result['content']);
            
            if (!$jsonValidation['valid']) {
                foreach ($jsonValidation['errors'] as $error) {
                    error_log("FileManager::loadConfigFile JSON error: " . $error['message']);
                }
                return null;
            }
            
            $data = json_decode($result['content'], true);
            return $data;
            
        } catch (Exception $e) {
            error_log("FileManager::loadConfigFile exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if a configuration file exists
     */
    public function configFileExists(string $name): bool
    {
        $filePath = $this->getConfigFilePath($name);
        return file_exists($filePath);
    }

    /**
     * Delete a configuration file with enhanced error handling
     */
    public function deleteConfigFile(string $name): bool
    {
        try {
            $filePath = $this->getConfigFilePath($name);
            
            // Use enhanced file deletion with error handling
            $result = $this->errorHandler->deleteFileSafely($filePath);
            
            if (!$result['success']) {
                foreach ($result['errors'] as $error) {
                    error_log("FileManager::deleteConfigFile error: " . $error['message']);
                }
                return false;
            }
            
            // Log warnings if any
            foreach ($result['warnings'] as $warning) {
                error_log("FileManager::deleteConfigFile warning: " . $warning);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("FileManager::deleteConfigFile exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * List all configuration files
     */
    public function listConfigFiles(): array
    {
        try {
            if (!is_dir($this->configDirectory)) {
                return [];
            }
            
            $files = [];
            $iterator = new \DirectoryIterator($this->configDirectory);
            
            foreach ($iterator as $file) {
                if ($file->isDot() || !$file->isFile()) {
                    continue;
                }
                
                $filename = $file->getFilename();
                
                // Only include .json files
                if (pathinfo($filename, PATHINFO_EXTENSION) !== 'json') {
                    continue;
                }
                
                $name = pathinfo($filename, PATHINFO_FILENAME);
                $files[] = [
                    'name' => $name,
                    'filename' => $filename,
                    'path' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime(),
                    'created' => $file->getCTime()
                ];
            }
            
            // Sort by modification time (newest first)
            usort($files, function($a, $b) {
                return $b['modified'] - $a['modified'];
            });
            
            return $files;
        } catch (Exception $e) {
            error_log("FileManager::listConfigFiles error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get file information for a specific configuration
     */
    public function getConfigFileInfo(string $name): ?array
    {
        try {
            $filePath = $this->getConfigFilePath($name);
            
            if (!file_exists($filePath)) {
                return null;
            }
            
            $stat = stat($filePath);
            
            if ($stat === false) {
                return null;
            }
            
            return [
                'name' => $name,
                'path' => $filePath,
                'size' => $stat['size'],
                'modified' => $stat['mtime'],
                'created' => $stat['ctime'],
                'readable' => is_readable($filePath),
                'writable' => is_writable($filePath)
            ];
        } catch (Exception $e) {
            error_log("FileManager::getConfigFileInfo error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Backup a configuration file
     */
    public function backupConfigFile(string $name): bool
    {
        try {
            $filePath = $this->getConfigFilePath($name);
            
            if (!file_exists($filePath)) {
                return false;
            }
            
            $backupPath = $filePath . '.backup.' . date('Y-m-d_H-i-s');
            
            if (!copy($filePath, $backupPath)) {
                throw new Exception("Failed to create backup: {$backupPath}");
            }
            
            return true;
        } catch (Exception $e) {
            error_log("FileManager::backupConfigFile error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate JSON content
     */
    public function validateJsonContent(string $jsonContent): array
    {
        $errors = [];
        
        if (empty(trim($jsonContent))) {
            $errors[] = 'JSON content is empty';
            return $errors;
        }
        
        json_decode($jsonContent);
        $jsonError = json_last_error();
        
        if ($jsonError !== JSON_ERROR_NONE) {
            $errors[] = 'Invalid JSON: ' . json_last_error_msg();
        }
        
        return $errors;
    }

    /**
     * Get disk space information for the configuration directory
     */
    public function getDiskSpaceInfo(): array
    {
        try {
            $freeBytes = disk_free_space($this->configDirectory);
            $totalBytes = disk_total_space($this->configDirectory);
            
            return [
                'free_bytes' => $freeBytes,
                'total_bytes' => $totalBytes,
                'used_bytes' => $totalBytes - $freeBytes,
                'free_mb' => round($freeBytes / 1024 / 1024, 2),
                'total_mb' => round($totalBytes / 1024 / 1024, 2),
                'used_mb' => round(($totalBytes - $freeBytes) / 1024 / 1024, 2)
            ];
        } catch (Exception $e) {
            error_log("FileManager::getDiskSpaceInfo error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Validate configuration directory health
     * 
     * @return array Health check result
     */
    public function validateConfigurationDirectoryHealth(): array
    {
        return $this->errorHandler->validateDirectoryAccess($this->configDirectory);
    }

    /**
     * Get detailed file operation result with error handling
     * 
     * @param string $operation Operation name (save, load, delete)
     * @param string $name Configuration name
     * @param mixed $data Optional data for save operations
     * @return array Detailed operation result
     */
    public function performFileOperationWithErrorHandling(string $operation, string $name, $data = null): array
    {
        $result = [
            'success' => false,
            'operation' => $operation,
            'name' => $name,
            'errors' => [],
            'warnings' => [],
            'data' => null
        ];

        try {
            switch ($operation) {
                case 'save':
                    if ($data === null) {
                        $result['errors'][] = 'No data provided for save operation';
                        break;
                    }
                    $result['success'] = $this->saveConfigFile($name, $data);
                    break;

                case 'load':
                    $loadedData = $this->loadConfigFile($name);
                    $result['success'] = $loadedData !== null;
                    $result['data'] = $loadedData;
                    break;

                case 'delete':
                    $result['success'] = $this->deleteConfigFile($name);
                    break;

                default:
                    $result['errors'][] = "Unknown operation: {$operation}";
                    break;
            }

            // Add directory health check warnings
            $healthCheck = $this->validateConfigurationDirectoryHealth();
            if (!empty($healthCheck['warnings'])) {
                $result['warnings'] = array_merge($result['warnings'], $healthCheck['warnings']);
            }

        } catch (Exception $e) {
            $result['errors'][] = "Exception in {$operation} operation: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Get user-friendly error message for file operations
     * 
     * @param array $operationResult Result from performFileOperationWithErrorHandling
     * @return string User-friendly error message
     */
    public function getUserFriendlyErrorMessage(array $operationResult): string
    {
        if ($operationResult['success']) {
            return "Operation '{$operationResult['operation']}' completed successfully";
        }

        $messages = [];
        $messages[] = "Failed to {$operationResult['operation']} configuration '{$operationResult['name']}'";

        if (!empty($operationResult['errors'])) {
            $messages[] = "Errors: " . implode(', ', $operationResult['errors']);
        }

        if (!empty($operationResult['warnings'])) {
            $messages[] = "Warnings: " . implode(', ', $operationResult['warnings']);
        }

        return implode("\n", $messages);
    }

    /**
     * Get file system error handler instance
     * 
     * @return FileSystemErrorHandler
     */
    public function getErrorHandler(): FileSystemErrorHandler
    {
        return $this->errorHandler;
    }
}