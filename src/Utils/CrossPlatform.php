<?php

namespace OhaGui\Utils;

/**
 * Cross-platform utility class for OS detection and path handling
 * Handles differences between Windows, macOS, and Linux operating systems
 */
class CrossPlatform
{
    const OS_WINDOWS = 'windows';
    const OS_MACOS = 'macos';
    const OS_LINUX = 'linux';
    const OS_UNKNOWN = 'unknown';

    /**
     * Detect the current operating system
     * 
     * @return string One of the OS constants
     */
    public static function getOperatingSystem(): string
    {
        $os = strtolower(PHP_OS);
        
        if (str_starts_with($os, 'win')) {
            return self::OS_WINDOWS;
        } elseif (str_starts_with($os, 'darwin')) {
            return self::OS_MACOS;
        } elseif (str_starts_with($os, 'linux')) {
            return self::OS_LINUX;
        }
        
        return self::OS_UNKNOWN;
    }

    /**
     * Check if running on Windows
     * 
     * @return bool
     */
    public static function isWindows(): bool
    {
        return self::getOperatingSystem() === self::OS_WINDOWS;
    }

    /**
     * Check if running on macOS
     * 
     * @return bool
     */
    public static function isMacOS(): bool
    {
        return self::getOperatingSystem() === self::OS_MACOS;
    }

    /**
     * Check if running on Linux
     * 
     * @return bool
     */
    public static function isLinux(): bool
    {
        return self::getOperatingSystem() === self::OS_LINUX;
    }

    /**
     * Get the appropriate directory separator for the current OS
     * 
     * @return string
     */
    public static function getDirectorySeparator(): string
    {
        return DIRECTORY_SEPARATOR;
    }

    /**
     * Normalize a file path for the current operating system
     * 
     * @param string $path
     * @return string
     */
    public static function normalizePath(string $path): string
    {
        // Replace forward slashes and backslashes with the appropriate separator
        $normalized = str_replace(['/', '\\'], self::getDirectorySeparator(), $path);
        
        // Remove duplicate separators
        $separator = self::getDirectorySeparator();
        $normalized = preg_replace('#' . preg_quote($separator) . '+#', $separator, $normalized);
        
        return $normalized;
    }

    /**
     * Join path components using the appropriate directory separator
     * 
     * @param string ...$parts
     * @return string
     */
    public static function joinPath(string ...$parts): string
    {
        $separator = self::getDirectorySeparator();
        $path = implode($separator, array_filter($parts, 'strlen'));
        
        return self::normalizePath($path);
    }

    /**
     * Get the user's home directory path
     * 
     * @return string
     */
    public static function getHomeDirectory(): string
    {
        if (self::isWindows()) {
            return $_SERVER['USERPROFILE'] ?? $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
        }
        
        return $_SERVER['HOME'] ?? '/tmp';
    }

    /**
     * Get the appropriate executable extension for the current OS
     * 
     * @return string
     */
    public static function getExecutableExtension(): string
    {
        return self::isWindows() ? '.exe' : '';
    }

    /**
     * Find the oha binary path on the current system
     * Searches project bin directory, common installation locations and PATH
     * 
     * @return string|null Path to oha binary or null if not found
     */
    public static function findOhaBinaryPath(): ?string
    {
        $binaryName = 'oha' . self::getExecutableExtension();
        
        // First, check the project's bin directory (highest priority)
        $projectBinPath = self::getProjectBinPath($binaryName);
        if ($projectBinPath !== null) {
            return $projectBinPath;
        }
        
        // Second, try to find oha in PATH
        $pathResult = self::findInPath($binaryName);
        if ($pathResult !== null) {
            return $pathResult;
        }
        
        // Finally, search common installation locations
        $commonPaths = self::getCommonOhaPaths();
        
        foreach ($commonPaths as $path) {
            $fullPath = self::joinPath($path, $binaryName);
            if (file_exists($fullPath) && is_executable($fullPath)) {
                return $fullPath;
            }
        }
        
        return null;
    }

    /**
     * Check for oha binary in the project's bin directory
     * 
     * @param string $binaryName
     * @return string|null
     */
    private static function getProjectBinPath(string $binaryName): ?string
    {
        // Get the project root directory (where composer.json is located)
        $projectRoot = self::getProjectRoot();
        if ($projectRoot === null) {
            return null;
        }
        
        $binPath = self::joinPath($projectRoot, 'bin', $binaryName);
        
        if (file_exists($binPath) && is_executable($binPath)) {
            return $binPath;
        }
        
        return null;
    }

    /**
     * Find the project root directory by looking for composer.json
     * 
     * @return string|null
     */
    private static function getProjectRoot(): ?string
    {
        $currentDir = __DIR__;
        
        // Walk up the directory tree looking for composer.json
        while ($currentDir !== dirname($currentDir)) {
            $composerPath = self::joinPath($currentDir, 'composer.json');
            if (file_exists($composerPath)) {
                return $currentDir;
            }
            $currentDir = dirname($currentDir);
        }
        
        // Also check if we're running from the project root directly
        if (file_exists('composer.json')) {
            return getcwd();
        }
        
        return null;
    }

    /**
     * Search for a binary in the system PATH
     * 
     * @param string $binaryName
     * @return string|null
     */
    private static function findInPath(string $binaryName): ?string
    {
        $pathSeparator = self::isWindows() ? ';' : ':';
        $paths = explode($pathSeparator, $_SERVER['PATH'] ?? '');
        
        foreach ($paths as $path) {
            $fullPath = self::joinPath(trim($path), $binaryName);
            if (file_exists($fullPath) && is_executable($fullPath)) {
                return $fullPath;
            }
        }
        
        return null;
    }

    /**
     * Get common installation paths for oha binary based on OS
     * 
     * @return array
     */
    private static function getCommonOhaPaths(): array
    {
        $homeDir = self::getHomeDirectory();
        
        switch (self::getOperatingSystem()) {
            case self::OS_WINDOWS:
                return [
                    'C:\\Program Files\\oha',
                    'C:\\Program Files (x86)\\oha',
                    self::joinPath($homeDir, 'AppData', 'Local', 'oha'),
                    self::joinPath($homeDir, '.cargo', 'bin'),
                    'C:\\tools\\oha',
                ];
                
            case self::OS_MACOS:
                return [
                    '/usr/local/bin',
                    '/opt/homebrew/bin',
                    '/usr/bin',
                    self::joinPath($homeDir, '.cargo', 'bin'),
                    self::joinPath($homeDir, 'bin'),
                    '/opt/local/bin',
                ];
                
            case self::OS_LINUX:
                return [
                    '/usr/local/bin',
                    '/usr/bin',
                    '/bin',
                    self::joinPath($homeDir, '.cargo', 'bin'),
                    self::joinPath($homeDir, 'bin'),
                    self::joinPath($homeDir, '.local', 'bin'),
                    '/snap/bin',
                ];
                
            default:
                return [
                    '/usr/local/bin',
                    '/usr/bin',
                    '/bin',
                    self::joinPath($homeDir, 'bin'),
                ];
        }
    }

    /**
     * Get the appropriate configuration directory for the application
     * 
     * @return string
     */
    public static function getConfigDirectory(): string
    {
        $homeDir = self::getHomeDirectory();
        
        switch (self::getOperatingSystem()) {
            case self::OS_WINDOWS:
                $appData = $_SERVER['APPDATA'] ?? self::joinPath($homeDir, 'AppData', 'Roaming');
                return self::joinPath($appData, 'OhaGui');
                
            case self::OS_MACOS:
                return self::joinPath($homeDir, 'Library', 'Application Support', 'OhaGui');
                
            case self::OS_LINUX:
            default:
                $configHome = $_SERVER['XDG_CONFIG_HOME'] ?? self::joinPath($homeDir, '.config');
                return self::joinPath($configHome, 'oha-gui');
        }
    }

    /**
     * Get the oha binary path (alias for findOhaBinaryPath for backward compatibility)
     * 
     * @return string|null
     */
    public static function getOhaBinaryPath(): ?string
    {
        return self::findOhaBinaryPath();
    }

    /**
     * Open a directory in the system's file manager
     * 
     * @param string $directoryPath The directory path to open
     * @return bool True if successful, false otherwise
     */
    public static function openDirectory(string $directoryPath): bool
    {
        if (!is_dir($directoryPath)) {
            return false;
        }
        
        try {
            if (self::isWindows()) {
                // Windows: use explorer
                $escapedPath = self::escapeShellArgument($directoryPath);
                exec("explorer $escapedPath", $output, $returnCode);
                return $returnCode === 0;
            } elseif (self::isMacOS()) {
                // macOS: use open command
                $escapedPath = self::escapeShellArgument($directoryPath);
                exec("open $escapedPath", $output, $returnCode);
                return $returnCode === 0;
            } else {
                // Linux: try xdg-open first, then gnome-open, then kde-open
                $escapedPath = self::escapeShellArgument($directoryPath);
                $commands = ['xdg-open', 'gnome-open', 'kde-open'];
                
                foreach ($commands as $command) {
                    exec("which $command", $output, $returnCode);
                    if ($returnCode === 0) {
                        exec("$command $escapedPath", $output, $returnCode);
                        return $returnCode === 0;
                    }
                }
                
                // If none of the commands work, return false
                return false;
            }
        } catch (Exception $e) {
            error_log("Error opening directory: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Escape a command argument for safe shell execution
     * 
     * @param string $argument
     * @return string
     */
    public static function escapeShellArgument(string $argument): string
    {
        if (self::isWindows()) {
            // Windows command line escaping
            if (strpos($argument, ' ') !== false || strpos($argument, '"') !== false) {
                $argument = '"' . str_replace('"', '""', $argument) . '"';
            }
            return $argument;
        } else {
            // Unix-like systems
            return escapeshellarg($argument);
        }
    }
}