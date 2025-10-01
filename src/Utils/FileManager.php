<?php

namespace OhaGui\Utils;

use Exception;
use RuntimeException;

/**
 * File manager class for configuration file operations
 * Handles reading, writing, and managing configuration files
 */
class FileManager
{
    private string $configDirectory;

    /**
     * Constructor
     * 
     * @param string|null $configDirectory Optional custom config directory
     */
    public function __construct(?string $configDirectory = null)
    {
        $this->configDirectory = $configDirectory ?? CrossPlatform::getConfigDirectory();
        $this->ensureConfigDirectoryExists();
    }

    /**
     * Get the configuration directory path
     * 
     * @return string
     */
    public function getConfigDirectory(): string
    {
        return $this->configDirectory;
    }

    /**
     * Ensure the configuration directory exists
     * 
     * @throws RuntimeException If directory cannot be created
     */
    private function ensureConfigDirectoryExists(): void
    {
        if (!is_dir($this->configDirectory)) {
            // Try to create the directory with proper error handling
            $created = @mkdir($this->configDirectory, 0755, true);
            
            if (!$created) {
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : 'Unknown error';
                
                // Provide specific error messages based on common issues
                if (strpos($errorMsg, 'Permission denied') !== false) {
                    throw new RuntimeException(
                        "Permission denied: Cannot create configuration directory '{$this->configDirectory}'. " .
                        "Please check parent directory permissions or run with appropriate privileges."
                    );
                } elseif (strpos($errorMsg, 'No space left') !== false) {
                    throw new RuntimeException(
                        "Insufficient disk space: Cannot create configuration directory '{$this->configDirectory}'. " .
                        "Please free up disk space and try again."
                    );
                } elseif (strpos($errorMsg, 'File exists') !== false) {
                    throw new RuntimeException(
                        "Path conflict: A file exists at '{$this->configDirectory}' where directory should be created. " .
                        "Please remove the file or choose a different configuration directory."
                    );
                } else {
                    throw new RuntimeException(
                        "Failed to create configuration directory '{$this->configDirectory}': {$errorMsg}"
                    );
                }
            }
        }

        // Check if directory is writable
        if (!is_writable($this->configDirectory)) {
            // Try to fix permissions if possible
            if (!@chmod($this->configDirectory, 0755)) {
                throw new RuntimeException(
                    "Configuration directory is not writable: '{$this->configDirectory}'. " .
                    "Please check directory permissions. Required permissions: read, write, execute for owner."
                );
            }
        }

        // Additional checks for directory health
        if (!is_readable($this->configDirectory)) {
            throw new RuntimeException(
                "Configuration directory is not readable: '{$this->configDirectory}'. " .
                "Please check directory permissions."
            );
        }
    }

    /**
     * Get the full path for a configuration file
     * 
     * @param string $filename
     * @return string
     */
    public function getConfigFilePath(string $filename): string
    {
        // Sanitize filename to prevent directory traversal
        $filename = $this->sanitizeFilename($filename);
        
        // Ensure .json extension
        if (!str_ends_with(strtolower($filename), '.json')) {
            $filename .= '.json';
        }

        return CrossPlatform::joinPath($this->configDirectory, $filename);
    }

    /**
     * Sanitize a filename to prevent security issues
     * 
     * @param string $filename
     * @return string
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove directory separators and other dangerous characters
        $filename = preg_replace('/[\/\\\\:*?"<>|]/', '', $filename);
        
        // Remove leading/trailing dots and spaces
        $filename = trim($filename, '. ');
        
        // Ensure filename is not empty
        if (empty($filename)) {
            throw new RuntimeException("Invalid filename provided");
        }

        return $filename;
    }

    /**
     * Save data to a configuration file
     * 
     * @param string $filename
     * @param array $data
     * @return bool
     * @throws RuntimeException If file cannot be written
     */
    public function saveConfigFile(string $filename, array $data): bool
    {
        $filePath = $this->getConfigFilePath($filename);
        
        // Check disk space before attempting to write
        $diskInfo = $this->getDiskSpaceInfo();
        $estimatedSize = strlen(json_encode($data)) * 2; // Rough estimate with formatting
        
        if ($diskInfo['free'] < $estimatedSize + 1024) { // Add 1KB buffer
            throw new RuntimeException(
                "Insufficient disk space to save configuration file '{$filename}'. " .
                "Available: " . $this->formatBytes($diskInfo['free']) . 
                ", Required: ~" . $this->formatBytes($estimatedSize)
            );
        }
        
        try {
            $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if ($jsonData === false) {
                $jsonError = json_last_error_msg();
                throw new RuntimeException("Failed to encode data to JSON: {$jsonError}");
            }

            // Write to temporary file first, then rename for atomic operation
            $tempFile = $filePath . '.tmp';
            
            // Ensure we can write to the directory
            if (!is_writable(dirname($filePath))) {
                throw new RuntimeException(
                    "Cannot write to configuration directory: " . dirname($filePath) . 
                    ". Please check directory permissions."
                );
            }
            
            $bytesWritten = @file_put_contents($tempFile, $jsonData, LOCK_EX);
            
            if ($bytesWritten === false) {
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : 'Unknown error';
                
                // Provide specific error messages
                if (strpos($errorMsg, 'Permission denied') !== false) {
                    throw new RuntimeException(
                        "Permission denied: Cannot write to temporary file '{$tempFile}'. " .
                        "Please check directory permissions."
                    );
                } elseif (strpos($errorMsg, 'No space left') !== false) {
                    throw new RuntimeException(
                        "Disk full: Cannot write configuration file '{$filename}'. " .
                        "Please free up disk space and try again."
                    );
                } else {
                    throw new RuntimeException("Failed to write to temporary file '{$tempFile}': {$errorMsg}");
                }
            }

            // Verify the written data
            if ($bytesWritten !== strlen($jsonData)) {
                @unlink($tempFile);
                throw new RuntimeException(
                    "Data integrity error: Expected to write " . strlen($jsonData) . 
                    " bytes but wrote {$bytesWritten} bytes to '{$tempFile}'"
                );
            }

            // Atomic rename operation
            if (!@rename($tempFile, $filePath)) {
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : 'Unknown error';
                @unlink($tempFile); // Clean up temp file
                
                throw new RuntimeException(
                    "Failed to finalize configuration file '{$filename}': {$errorMsg}. " .
                    "The file may be locked by another process."
                );
            }

            // Verify the final file exists and has correct size
            if (!file_exists($filePath) || filesize($filePath) !== strlen($jsonData)) {
                throw new RuntimeException(
                    "File verification failed after saving '{$filename}'. " .
                    "The file may be corrupted or the filesystem may have issues."
                );
            }

            return true;
            
        } catch (Exception $e) {
            // Clean up temp file if it exists
            if (isset($tempFile) && file_exists($tempFile)) {
                @unlink($tempFile);
            }
            
            // Re-throw with enhanced error message
            if ($e instanceof RuntimeException) {
                throw $e;
            } else {
                throw new RuntimeException("Failed to save configuration file '{$filename}': " . $e->getMessage());
            }
        }
    }

    /**
     * Load data from a configuration file
     * 
     * @param string $filename
     * @return array|null Returns null if file doesn't exist
     * @throws RuntimeException If file exists but cannot be read or parsed
     */
    public function loadConfigFile(string $filename): ?array
    {
        $filePath = $this->getConfigFilePath($filename);
        
        if (!file_exists($filePath)) {
            return null;
        }

        if (!is_readable($filePath)) {
            throw new RuntimeException(
                "Configuration file is not readable: '{$filePath}'. " .
                "Please check file permissions. Required: read permission for current user."
            );
        }

        // Check if file is empty or too large
        $fileSize = filesize($filePath);
        if ($fileSize === false) {
            throw new RuntimeException("Cannot determine size of configuration file: '{$filePath}'");
        }
        
        if ($fileSize === 0) {
            throw new RuntimeException(
                "Configuration file is empty: '{$filename}'. " .
                "The file may be corrupted or was not saved properly."
            );
        }
        
        if ($fileSize > 10 * 1024 * 1024) { // 10MB limit
            throw new RuntimeException(
                "Configuration file is too large: '{$filename}' (" . $this->formatBytes($fileSize) . "). " .
                "Maximum allowed size is 10MB."
            );
        }

        try {
            $content = @file_get_contents($filePath);
            
            if ($content === false) {
                $error = error_get_last();
                $errorMsg = $error ? $error['message'] : 'Unknown error';
                
                if (strpos($errorMsg, 'Permission denied') !== false) {
                    throw new RuntimeException(
                        "Permission denied: Cannot read configuration file '{$filename}'. " .
                        "Please check file permissions."
                    );
                } elseif (strpos($errorMsg, 'No such file') !== false) {
                    throw new RuntimeException(
                        "Configuration file disappeared during read: '{$filename}'. " .
                        "The file may have been deleted by another process."
                    );
                } else {
                    throw new RuntimeException("Failed to read configuration file '{$filename}': {$errorMsg}");
                }
            }

            // Validate content is not binary
            if (!mb_check_encoding($content, 'UTF-8')) {
                throw new RuntimeException(
                    "Configuration file contains invalid UTF-8 encoding: '{$filename}'. " .
                    "The file may be corrupted or is not a text file."
                );
            }

            // Attempt to parse JSON with detailed error reporting
            $data = json_decode($content, true);
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                $jsonError = json_last_error_msg();
                $jsonErrorCode = json_last_error();
                
                // Provide specific JSON error guidance
                $errorGuidance = $this->getJsonErrorGuidance($jsonErrorCode, $content);
                
                throw new RuntimeException(
                    "Failed to parse JSON in configuration file '{$filename}': {$jsonError}. {$errorGuidance}"
                );
            }

            // Validate that we got an array (object in JSON)
            if (!is_array($data)) {
                throw new RuntimeException(
                    "Configuration file '{$filename}' does not contain a valid JSON object. " .
                    "Expected object, got " . gettype($data) . "."
                );
            }

            return $data;
            
        } catch (Exception $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            } else {
                throw new RuntimeException("Failed to load configuration file '{$filename}': " . $e->getMessage());
            }
        }
    }

    /**
     * Check if a configuration file exists
     * 
     * @param string $filename
     * @return bool
     */
    public function configFileExists(string $filename): bool
    {
        $filePath = $this->getConfigFilePath($filename);
        return file_exists($filePath);
    }

    /**
     * Delete a configuration file
     * 
     * @param string $filename
     * @return bool
     * @throws RuntimeException If file exists but cannot be deleted
     */
    public function deleteConfigFile(string $filename): bool
    {
        $filePath = $this->getConfigFilePath($filename);
        
        if (!file_exists($filePath)) {
            return true; // File doesn't exist, consider it deleted
        }

        if (!unlink($filePath)) {
            throw new RuntimeException("Failed to delete configuration file: {$filePath}");
        }

        return true;
    }

    /**
     * List all configuration files in the directory
     * 
     * @return array Array of filenames (without .json extension)
     */
    public function listConfigFiles(): array
    {
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
            if (str_ends_with(strtolower($filename), '.json')) {
                // Remove .json extension for the returned list
                $files[] = substr($filename, 0, -5);
            }
        }

        sort($files);
        return $files;
    }

    /**
     * Get file modification time for a configuration file
     * 
     * @param string $filename
     * @return int|null Unix timestamp or null if file doesn't exist
     */
    public function getConfigFileModificationTime(string $filename): ?int
    {
        $filePath = $this->getConfigFilePath($filename);
        
        if (!file_exists($filePath)) {
            return null;
        }

        $mtime = filemtime($filePath);
        return $mtime !== false ? $mtime : null;
    }

    /**
     * Get file size for a configuration file
     * 
     * @param string $filename
     * @return int|null File size in bytes or null if file doesn't exist
     */
    public function getConfigFileSize(string $filename): ?int
    {
        $filePath = $this->getConfigFilePath($filename);
        
        if (!file_exists($filePath)) {
            return null;
        }

        $size = filesize($filePath);
        return $size !== false ? $size : null;
    }

    /**
     * Backup a configuration file
     * 
     * @param string $filename
     * @return string|null Path to backup file or null if original doesn't exist
     * @throws RuntimeException If backup cannot be created
     */
    public function backupConfigFile(string $filename): ?string
    {
        $filePath = $this->getConfigFilePath($filename);
        
        if (!file_exists($filePath)) {
            return null;
        }

        $timestamp = date('Y-m-d_H-i-s');
        $backupFilename = pathinfo($filename, PATHINFO_FILENAME) . "_backup_{$timestamp}.json";
        $backupPath = $this->getConfigFilePath($backupFilename);

        if (!copy($filePath, $backupPath)) {
            throw new RuntimeException("Failed to create backup of configuration file: {$filename}");
        }

        return $backupPath;
    }

    /**
     * Validate that a configuration file contains valid JSON
     * 
     * @param string $filename
     * @return bool
     */
    public function validateConfigFile(string $filename): bool
    {
        try {
            $data = $this->loadConfigFile($filename);
            return $data !== null;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    /**
     * Get disk space information for the configuration directory
     * 
     * @return array Array with 'free', 'total', and 'used' bytes
     */
    public function getDiskSpaceInfo(): array
    {
        $freeBytes = @disk_free_space($this->configDirectory);
        $totalBytes = @disk_total_space($this->configDirectory);
        
        return [
            'free' => $freeBytes !== false ? $freeBytes : 0,
            'total' => $totalBytes !== false ? $totalBytes : 0,
            'used' => ($totalBytes !== false && $freeBytes !== false) ? ($totalBytes - $freeBytes) : 0,
        ];
    }

    /**
     * Format bytes into human-readable format
     * 
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Get specific guidance for JSON parsing errors
     * 
     * @param int $errorCode JSON error code
     * @param string $content File content for context
     * @return string Error guidance message
     */
    private function getJsonErrorGuidance(int $errorCode, string $content): string
    {
        switch ($errorCode) {
            case JSON_ERROR_SYNTAX:
                // Try to find the approximate location of syntax error
                $lines = explode("\n", $content);
                $lineCount = count($lines);
                
                return "Syntax error in JSON. Check for missing commas, brackets, or quotes. " .
                       "File has {$lineCount} lines. Common issues: trailing commas, unescaped quotes.";
                       
            case JSON_ERROR_UTF8:
                return "Invalid UTF-8 encoding. The file may contain non-UTF-8 characters. " .
                       "Try saving the file with UTF-8 encoding.";
                       
            case JSON_ERROR_DEPTH:
                return "JSON structure is too deeply nested. Maximum nesting depth exceeded.";
                
            case JSON_ERROR_CTRL_CHAR:
                return "Control character error. The JSON contains unescaped control characters. " .
                       "Check for unescaped newlines, tabs, or other control characters in strings.";
                       
            case JSON_ERROR_STATE_MISMATCH:
                return "Invalid or malformed JSON structure. Check that all brackets and braces are properly matched.";
                
            default:
                return "Please check the file format and ensure it contains valid JSON.";
        }
    }

    /**
     * Attempt to recover from file system errors
     * 
     * @param string $filename
     * @param string $operation
     * @return bool True if recovery was attempted
     */
    public function attemptRecovery(string $filename, string $operation): bool
    {
        $filePath = $this->getConfigFilePath($filename);
        
        switch ($operation) {
            case 'read':
                // Try to recover read permissions
                if (file_exists($filePath) && !is_readable($filePath)) {
                    return @chmod($filePath, 0644);
                }
                break;
                
            case 'write':
                // Try to recover write permissions
                if (file_exists($filePath) && !is_writable($filePath)) {
                    return @chmod($filePath, 0644);
                }
                
                // Try to recover directory write permissions
                if (!is_writable($this->configDirectory)) {
                    return @chmod($this->configDirectory, 0755);
                }
                break;
                
            case 'delete':
                // Try to recover delete permissions
                if (file_exists($filePath) && !is_writable(dirname($filePath))) {
                    return @chmod(dirname($filePath), 0755);
                }
                break;
        }
        
        return false;
    }

    /**
     * Check file system health
     * 
     * @return array Health check results
     */
    public function checkFileSystemHealth(): array
    {
        $results = [
            'directory_exists' => is_dir($this->configDirectory),
            'directory_readable' => is_readable($this->configDirectory),
            'directory_writable' => is_writable($this->configDirectory),
            'disk_space_available' => true,
            'permissions_ok' => true,
            'issues' => []
        ];
        
        // Check disk space
        $diskInfo = $this->getDiskSpaceInfo();
        if ($diskInfo['free'] < 1024 * 1024) { // Less than 1MB
            $results['disk_space_available'] = false;
            $results['issues'][] = 'Low disk space: ' . $this->formatBytes($diskInfo['free']) . ' remaining';
        }
        
        // Check directory permissions
        if (!$results['directory_readable']) {
            $results['permissions_ok'] = false;
            $results['issues'][] = 'Configuration directory is not readable';
        }
        
        if (!$results['directory_writable']) {
            $results['permissions_ok'] = false;
            $results['issues'][] = 'Configuration directory is not writable';
        }
        
        // Check for corrupted files
        $configFiles = $this->listConfigFiles();
        $corruptedFiles = [];
        
        foreach ($configFiles as $filename) {
            if (!$this->validateConfigFile($filename)) {
                $corruptedFiles[] = $filename;
            }
        }
        
        if (!empty($corruptedFiles)) {
            $results['issues'][] = 'Corrupted configuration files: ' . implode(', ', $corruptedFiles);
        }
        
        $results['overall_health'] = empty($results['issues']);
        
        return $results;
    }
}