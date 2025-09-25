<?php

namespace OhaGui\Core;

use Exception;
use OhaGui\Utils\CrossPlatform;

/**
 * File System Error Handler
 * Provides comprehensive error handling for file system operations
 */
class FileSystemErrorHandler
{
    /**
     * Error types
     */
    public const ERROR_PERMISSION_DENIED = 'permission_denied';
    public const ERROR_FILE_NOT_FOUND = 'file_not_found';
    public const ERROR_DIRECTORY_NOT_FOUND = 'directory_not_found';
    public const ERROR_DISK_FULL = 'disk_full';
    public const ERROR_INVALID_JSON = 'invalid_json';
    public const ERROR_FILE_LOCKED = 'file_locked';
    public const ERROR_INVALID_PATH = 'invalid_path';
    public const ERROR_FILE_TOO_LARGE = 'file_too_large';
    public const ERROR_DIRECTORY_NOT_WRITABLE = 'directory_not_writable';
    public const ERROR_UNKNOWN = 'unknown';

    /**
     * Maximum file size for configuration files (1MB)
     */
    private const MAX_FILE_SIZE = 1024 * 1024;

    /**
     * Minimum free disk space required (10MB)
     */
    private const MIN_FREE_SPACE = 10 * 1024 * 1024;

    /**
     * Validate directory access and permissions
     * 
     * @param string $directoryPath Directory path to validate
     * @return array Validation result
     */
    public function validateDirectoryAccess(string $directoryPath): array
    {
        $result = [
            'accessible' => false,
            'readable' => false,
            'writable' => false,
            'exists' => false,
            'errors' => [],
            'warnings' => []
        ];

        try {
            // Check if directory exists
            if (!is_dir($directoryPath)) {
                $result['errors'][] = [
                    'type' => self::ERROR_DIRECTORY_NOT_FOUND,
                    'message' => "Directory does not exist: {$directoryPath}",
                    'suggestion' => 'Create the directory or check the path'
                ];
                return $result;
            }

            $result['exists'] = true;

            // Check if directory is readable
            if (!is_readable($directoryPath)) {
                $result['errors'][] = [
                    'type' => self::ERROR_PERMISSION_DENIED,
                    'message' => "Directory is not readable: {$directoryPath}",
                    'suggestion' => $this->getPermissionSuggestion($directoryPath, 'read')
                ];
            } else {
                $result['readable'] = true;
            }

            // Check if directory is writable
            if (!is_writable($directoryPath)) {
                $result['errors'][] = [
                    'type' => self::ERROR_DIRECTORY_NOT_WRITABLE,
                    'message' => "Directory is not writable: {$directoryPath}",
                    'suggestion' => $this->getPermissionSuggestion($directoryPath, 'write')
                ];
            } else {
                $result['writable'] = true;
            }

            // Check disk space
            $diskSpaceResult = $this->checkDiskSpace($directoryPath);
            if (!$diskSpaceResult['sufficient']) {
                $result['warnings'][] = $diskSpaceResult['message'];
            }

            $result['accessible'] = $result['readable'] && $result['writable'];

        } catch (Exception $e) {
            $result['errors'][] = [
                'type' => self::ERROR_UNKNOWN,
                'message' => 'Error checking directory access: ' . $e->getMessage(),
                'suggestion' => 'Check the directory path and permissions'
            ];
        }

        return $result;
    }

    /**
     * Validate file access and permissions
     * 
     * @param string $filePath File path to validate
     * @return array Validation result
     */
    public function validateFileAccess(string $filePath): array
    {
        $result = [
            'accessible' => false,
            'readable' => false,
            'writable' => false,
            'exists' => false,
            'size' => 0,
            'errors' => [],
            'warnings' => []
        ];

        try {
            // Check if file exists
            if (!file_exists($filePath)) {
                $result['errors'][] = [
                    'type' => self::ERROR_FILE_NOT_FOUND,
                    'message' => "File does not exist: {$filePath}",
                    'suggestion' => 'Check the file path or create the file'
                ];
                return $result;
            }

            $result['exists'] = true;

            // Get file size
            $fileSize = filesize($filePath);
            if ($fileSize === false) {
                $result['errors'][] = [
                    'type' => self::ERROR_UNKNOWN,
                    'message' => "Cannot determine file size: {$filePath}",
                    'suggestion' => 'Check file permissions and integrity'
                ];
            } else {
                $result['size'] = $fileSize;

                // Check if file is too large
                if ($fileSize > self::MAX_FILE_SIZE) {
                    $result['warnings'][] = "File is large (" . $this->formatFileSize($fileSize) . ") - may impact performance";
                }
            }

            // Check if file is readable
            if (!is_readable($filePath)) {
                $result['errors'][] = [
                    'type' => self::ERROR_PERMISSION_DENIED,
                    'message' => "File is not readable: {$filePath}",
                    'suggestion' => $this->getPermissionSuggestion($filePath, 'read')
                ];
            } else {
                $result['readable'] = true;
            }

            // Check if file is writable
            if (!is_writable($filePath)) {
                $result['errors'][] = [
                    'type' => self::ERROR_PERMISSION_DENIED,
                    'message' => "File is not writable: {$filePath}",
                    'suggestion' => $this->getPermissionSuggestion($filePath, 'write')
                ];
            } else {
                $result['writable'] = true;
            }

            $result['accessible'] = $result['readable'] && $result['writable'];

        } catch (Exception $e) {
            $result['errors'][] = [
                'type' => self::ERROR_UNKNOWN,
                'message' => 'Error checking file access: ' . $e->getMessage(),
                'suggestion' => 'Check the file path and permissions'
            ];
        }

        return $result;
    }

    /**
     * Safely create directory with error handling
     * 
     * @param string $directoryPath Directory path to create
     * @param int $permissions Directory permissions (default: 0755)
     * @return array Creation result
     */
    public function createDirectorySafely(string $directoryPath, int $permissions = 0755): array
    {
        $result = [
            'success' => false,
            'created' => false,
            'errors' => [],
            'warnings' => []
        ];

        try {
            // Check if directory already exists
            if (is_dir($directoryPath)) {
                $result['success'] = true;
                $result['warnings'][] = "Directory already exists: {$directoryPath}";
                return $result;
            }

            // Validate parent directory
            $parentDir = dirname($directoryPath);
            if (!is_dir($parentDir)) {
                $result['errors'][] = [
                    'type' => self::ERROR_DIRECTORY_NOT_FOUND,
                    'message' => "Parent directory does not exist: {$parentDir}",
                    'suggestion' => 'Create parent directories first'
                ];
                return $result;
            }

            if (!is_writable($parentDir)) {
                $result['errors'][] = [
                    'type' => self::ERROR_PERMISSION_DENIED,
                    'message' => "Parent directory is not writable: {$parentDir}",
                    'suggestion' => $this->getPermissionSuggestion($parentDir, 'write')
                ];
                return $result;
            }

            // Check disk space
            $diskSpaceResult = $this->checkDiskSpace($parentDir);
            if (!$diskSpaceResult['sufficient']) {
                $result['errors'][] = [
                    'type' => self::ERROR_DISK_FULL,
                    'message' => $diskSpaceResult['message'],
                    'suggestion' => 'Free up disk space before creating directory'
                ];
                return $result;
            }

            // Create directory
            if (!mkdir($directoryPath, $permissions, true)) {
                $result['errors'][] = [
                    'type' => self::ERROR_PERMISSION_DENIED,
                    'message' => "Failed to create directory: {$directoryPath}",
                    'suggestion' => 'Check permissions and disk space'
                ];
                return $result;
            }

            $result['success'] = true;
            $result['created'] = true;

        } catch (Exception $e) {
            $result['errors'][] = [
                'type' => self::ERROR_UNKNOWN,
                'message' => 'Error creating directory: ' . $e->getMessage(),
                'suggestion' => 'Check the directory path and permissions'
            ];
        }

        return $result;
    }

    /**
     * Safely write file with error handling
     * 
     * @param string $filePath File path to write
     * @param string $content Content to write
     * @param bool $atomic Use atomic write operation
     * @return array Write result
     */
    public function writeFileSafely(string $filePath, string $content, bool $atomic = true): array
    {
        $result = [
            'success' => false,
            'bytes_written' => 0,
            'errors' => [],
            'warnings' => []
        ];

        try {
            // Validate directory
            $directory = dirname($filePath);
            $dirValidation = $this->validateDirectoryAccess($directory);
            
            if (!$dirValidation['accessible']) {
                $result['errors'] = array_merge($result['errors'], $dirValidation['errors']);
                return $result;
            }

            // Check if file exists and validate access
            if (file_exists($filePath)) {
                $fileValidation = $this->validateFileAccess($filePath);
                if (!$fileValidation['writable']) {
                    $result['errors'] = array_merge($result['errors'], $fileValidation['errors']);
                    return $result;
                }
            }

            // Check content size
            $contentSize = strlen($content);
            if ($contentSize > self::MAX_FILE_SIZE) {
                $result['warnings'][] = "Content is large (" . $this->formatFileSize($contentSize) . ") - may impact performance";
            }

            // Check disk space
            $diskSpaceResult = $this->checkDiskSpace($directory);
            if (!$diskSpaceResult['sufficient'] || $diskSpaceResult['free_bytes'] < $contentSize) {
                $result['errors'][] = [
                    'type' => self::ERROR_DISK_FULL,
                    'message' => 'Insufficient disk space for file write',
                    'suggestion' => 'Free up disk space before saving'
                ];
                return $result;
            }

            // Write file
            if ($atomic) {
                $result = $this->writeFileAtomic($filePath, $content);
            } else {
                $bytesWritten = file_put_contents($filePath, $content, LOCK_EX);
                if ($bytesWritten === false) {
                    $result['errors'][] = [
                        'type' => self::ERROR_FILE_LOCKED,
                        'message' => "Failed to write file: {$filePath}",
                        'suggestion' => 'Check if file is locked by another process'
                    ];
                } else {
                    $result['success'] = true;
                    $result['bytes_written'] = $bytesWritten;
                }
            }

        } catch (Exception $e) {
            $result['errors'][] = [
                'type' => self::ERROR_UNKNOWN,
                'message' => 'Error writing file: ' . $e->getMessage(),
                'suggestion' => 'Check the file path and permissions'
            ];
        }

        return $result;
    }

    /**
     * Safely read file with error handling
     * 
     * @param string $filePath File path to read
     * @return array Read result
     */
    public function readFileSafely(string $filePath): array
    {
        $result = [
            'success' => false,
            'content' => '',
            'size' => 0,
            'errors' => [],
            'warnings' => []
        ];

        try {
            // Validate file access
            $fileValidation = $this->validateFileAccess($filePath);
            
            if (!$fileValidation['exists']) {
                $result['errors'][] = [
                    'type' => self::ERROR_FILE_NOT_FOUND,
                    'message' => "File does not exist: {$filePath}",
                    'suggestion' => 'Check the file path'
                ];
                return $result;
            }

            if (!$fileValidation['readable']) {
                $result['errors'] = array_merge($result['errors'], $fileValidation['errors']);
                return $result;
            }

            // Add warnings from file validation
            $result['warnings'] = array_merge($result['warnings'], $fileValidation['warnings']);

            // Read file content
            $content = file_get_contents($filePath);
            
            if ($content === false) {
                $result['errors'][] = [
                    'type' => self::ERROR_FILE_LOCKED,
                    'message' => "Failed to read file: {$filePath}",
                    'suggestion' => 'Check if file is locked or corrupted'
                ];
                return $result;
            }

            $result['success'] = true;
            $result['content'] = $content;
            $result['size'] = strlen($content);

        } catch (Exception $e) {
            $result['errors'][] = [
                'type' => self::ERROR_UNKNOWN,
                'message' => 'Error reading file: ' . $e->getMessage(),
                'suggestion' => 'Check the file path and permissions'
            ];
        }

        return $result;
    }

    /**
     * Safely delete file with error handling
     * 
     * @param string $filePath File path to delete
     * @return array Delete result
     */
    public function deleteFileSafely(string $filePath): array
    {
        $result = [
            'success' => false,
            'deleted' => false,
            'errors' => [],
            'warnings' => []
        ];

        try {
            // Check if file exists
            if (!file_exists($filePath)) {
                $result['success'] = true;
                $result['warnings'][] = "File does not exist (already deleted): {$filePath}";
                return $result;
            }

            // Check if file is writable (needed for deletion)
            if (!is_writable($filePath)) {
                $result['errors'][] = [
                    'type' => self::ERROR_PERMISSION_DENIED,
                    'message' => "Cannot delete file (not writable): {$filePath}",
                    'suggestion' => $this->getPermissionSuggestion($filePath, 'write')
                ];
                return $result;
            }

            // Check if parent directory is writable
            $parentDir = dirname($filePath);
            if (!is_writable($parentDir)) {
                $result['errors'][] = [
                    'type' => self::ERROR_PERMISSION_DENIED,
                    'message' => "Cannot delete file (parent directory not writable): {$parentDir}",
                    'suggestion' => $this->getPermissionSuggestion($parentDir, 'write')
                ];
                return $result;
            }

            // Delete file
            if (!unlink($filePath)) {
                $result['errors'][] = [
                    'type' => self::ERROR_FILE_LOCKED,
                    'message' => "Failed to delete file: {$filePath}",
                    'suggestion' => 'Check if file is locked by another process'
                ];
                return $result;
            }

            $result['success'] = true;
            $result['deleted'] = true;

        } catch (Exception $e) {
            $result['errors'][] = [
                'type' => self::ERROR_UNKNOWN,
                'message' => 'Error deleting file: ' . $e->getMessage(),
                'suggestion' => 'Check the file path and permissions'
            ];
        }

        return $result;
    }

    /**
     * Validate JSON content with detailed error reporting
     * 
     * @param string $jsonContent JSON content to validate
     * @return array Validation result
     */
    public function validateJsonContent(string $jsonContent): array
    {
        $result = [
            'valid' => false,
            'errors' => [],
            'warnings' => []
        ];

        try {
            // Check if content is empty
            if (empty(trim($jsonContent))) {
                $result['errors'][] = [
                    'type' => self::ERROR_INVALID_JSON,
                    'message' => 'JSON content is empty',
                    'suggestion' => 'Provide valid JSON content'
                ];
                return $result;
            }

            // Check content size
            $contentSize = strlen($jsonContent);
            if ($contentSize > self::MAX_FILE_SIZE) {
                $result['warnings'][] = "JSON content is large (" . $this->formatFileSize($contentSize) . ")";
            }

            // Parse JSON
            json_decode($jsonContent);
            $jsonError = json_last_error();

            if ($jsonError !== JSON_ERROR_NONE) {
                $result['errors'][] = [
                    'type' => self::ERROR_INVALID_JSON,
                    'message' => 'Invalid JSON: ' . json_last_error_msg(),
                    'suggestion' => 'Check JSON syntax and formatting'
                ];
                return $result;
            }

            $result['valid'] = true;

        } catch (Exception $e) {
            $result['errors'][] = [
                'type' => self::ERROR_UNKNOWN,
                'message' => 'Error validating JSON: ' . $e->getMessage(),
                'suggestion' => 'Check the JSON content'
            ];
        }

        return $result;
    }

    /**
     * Get user-friendly error message
     * 
     * @param array $error Error details
     * @return string User-friendly message
     */
    public function getUserFriendlyErrorMessage(array $error): string
    {
        $message = $error['message'] ?? 'An unknown file system error occurred';
        
        if (!empty($error['suggestion'])) {
            $message .= "\n\nSuggestion: " . $error['suggestion'];
        }

        return $message;
    }

    /**
     * Write file atomically
     */
    private function writeFileAtomic(string $filePath, string $content): array
    {
        $result = [
            'success' => false,
            'bytes_written' => 0,
            'errors' => [],
            'warnings' => []
        ];

        $tempFile = $filePath . '.tmp.' . uniqid();

        try {
            // Write to temporary file
            $bytesWritten = file_put_contents($tempFile, $content, LOCK_EX);
            
            if ($bytesWritten === false) {
                $result['errors'][] = [
                    'type' => self::ERROR_FILE_LOCKED,
                    'message' => "Failed to write temporary file: {$tempFile}",
                    'suggestion' => 'Check disk space and permissions'
                ];
                return $result;
            }

            // Move temporary file to final location
            if (!rename($tempFile, $filePath)) {
                $result['errors'][] = [
                    'type' => self::ERROR_FILE_LOCKED,
                    'message' => "Failed to move temporary file to final location: {$filePath}",
                    'suggestion' => 'Check if target file is locked'
                ];
                return $result;
            }

            $result['success'] = true;
            $result['bytes_written'] = $bytesWritten;

        } catch (Exception $e) {
            $result['errors'][] = [
                'type' => self::ERROR_UNKNOWN,
                'message' => 'Error in atomic write: ' . $e->getMessage(),
                'suggestion' => 'Check file permissions and disk space'
            ];
        } finally {
            // Clean up temporary file if it still exists
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }

        return $result;
    }

    /**
     * Check disk space
     */
    private function checkDiskSpace(string $path): array
    {
        try {
            $freeBytes = disk_free_space($path);
            $totalBytes = disk_total_space($path);

            if ($freeBytes === false || $totalBytes === false) {
                return [
                    'sufficient' => true, // Assume sufficient if we can't check
                    'free_bytes' => 0,
                    'total_bytes' => 0,
                    'message' => 'Unable to check disk space'
                ];
            }

            $sufficient = $freeBytes >= self::MIN_FREE_SPACE;
            $message = $sufficient ? 
                'Sufficient disk space available' : 
                'Low disk space: ' . $this->formatFileSize($freeBytes) . ' free';

            return [
                'sufficient' => $sufficient,
                'free_bytes' => $freeBytes,
                'total_bytes' => $totalBytes,
                'message' => $message
            ];

        } catch (Exception $e) {
            return [
                'sufficient' => true, // Assume sufficient if we can't check
                'free_bytes' => 0,
                'total_bytes' => 0,
                'message' => 'Error checking disk space: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get permission suggestion based on OS
     */
    private function getPermissionSuggestion(string $path, string $operation): string
    {
        if (CrossPlatform::isWindows()) {
            return "Check Windows file/folder permissions for {$path}. You may need to run as administrator or change security settings.";
        } else {
            $permission = $operation === 'read' ? 'r' : 'w';
            return "Run: chmod +{$permission} {$path}";
        }
    }

    /**
     * Format file size for display
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        $size = $bytes;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }
}