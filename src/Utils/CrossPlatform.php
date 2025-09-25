<?php

namespace OhaGui\Utils;

/**
 * Cross-platform utility class for OS detection and path handling
 */
class CrossPlatform
{
    const OS_WINDOWS = 'windows';
    const OS_MACOS = 'macos';
    const OS_LINUX = 'linux';
    const OS_UNKNOWN = 'unknown';

    /**
     * Detect the current operating system
     */
    public static function getOperatingSystem(): string
    {
        $os = strtolower(PHP_OS);
        
        if (strpos($os, 'win') === 0) {
            return self::OS_WINDOWS;
        } elseif (strpos($os, 'darwin') === 0) {
            return self::OS_MACOS;
        } elseif (strpos($os, 'linux') === 0) {
            return self::OS_LINUX;
        }
        
        return self::OS_UNKNOWN;
    }

    /**
     * Check if running on Windows
     */
    public static function isWindows(): bool
    {
        return self::getOperatingSystem() === self::OS_WINDOWS;
    }

    /**
     * Check if running on macOS
     */
    public static function isMacOS(): bool
    {
        return self::getOperatingSystem() === self::OS_MACOS;
    }

    /**
     * Check if running on Linux
     */
    public static function isLinux(): bool
    {
        return self::getOperatingSystem() === self::OS_LINUX;
    }

    /**
     * Get the appropriate directory separator for the current OS
     */
    public static function getDirectorySeparator(): string
    {
        return DIRECTORY_SEPARATOR;
    }

    /**
     * Normalize path separators for the current operating system
     */
    public static function normalizePath(string $path): string
    {
        return str_replace(['/', '\\'], self::getDirectorySeparator(), $path);
    }

    /**
     * Join path components using the appropriate separator
     */
    public static function joinPaths(string ...$paths): string
    {
        $normalizedPaths = array_map([self::class, 'normalizePath'], $paths);
        return implode(self::getDirectorySeparator(), $normalizedPaths);
    }

    /**
     * Get the user's home directory path
     */
    public static function getHomeDirectory(): string
    {
        if (self::isWindows()) {
            return $_SERVER['USERPROFILE'] ?? $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
        }
        
        return $_SERVER['HOME'] ?? '/tmp';
    }

    /**
     * Get the appropriate configuration directory for the application
     */
    public static function getConfigDirectory(): string
    {
        $homeDir = self::getHomeDirectory();
        
        if (self::isWindows()) {
            $appData = $_SERVER['APPDATA'] ?? self::joinPaths($homeDir, 'AppData', 'Roaming');
            return self::joinPaths($appData, 'OhaGui');
        } elseif (self::isMacOS()) {
            return self::joinPaths($homeDir, 'Library', 'Application Support', 'OhaGui');
        } else {
            // Linux and other Unix-like systems
            $configHome = $_SERVER['XDG_CONFIG_HOME'] ?? self::joinPaths($homeDir, '.config');
            return self::joinPaths($configHome, 'ohagui');
        }
    }

    /**
     * Detect the oha binary path in the project's bin directory
     */
    public static function findOhaBinaryPath(): ?string
    {
        $binaryName = self::isWindows() ? 'oha.exe' : 'oha';
        
        // Check project bin directory
        $projectRoot = dirname(__DIR__, 2); // Go up from src/Utils to project root
        $binPath = self::joinPaths($projectRoot, 'bin', $binaryName);
        
        if (file_exists($binPath) && is_executable($binPath)) {
            return $binPath;
        }
        
        return null;
    }



    /**
     * Execute a command and return the result
     */
    public static function executeCommand(string $command): array
    {
        $output = [];
        $returnCode = 0;
        
        exec($command . ' 2>&1', $output, $returnCode);
        
        return [
            'output' => $output,
            'return_code' => $returnCode,
            'success' => $returnCode === 0
        ];
    }

    /**
     * Check if oha binary is available and working
     */
    public static function isOhaAvailable(): bool
    {
        $binaryPath = self::findOhaBinaryPath();
        
        if (!$binaryPath) {
            return false;
        }
        
        // Try to execute oha --version to verify it's working
        $command = escapeshellarg($binaryPath) . ' --version';
        $result = self::executeCommand($command);
        
        return $result['success'];
    }
}