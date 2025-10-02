<?php

namespace OhaGui\Utils;

use Kingbes\Libui\Window as LibuiWindow;
use FFI\CData;

/**
 * Window helper utility for cross-platform window operations
 * Provides functionality for window positioning and other window operations
 */
class WindowHelper
{
    /**
     * Center a window on the screen
     * 
     * @param CData $window The window to center
     * @return bool True if successful, false otherwise
     */
    public static function centerWindow(CData $window): bool
    {
        try {
            // Get screen dimensions
            $screenSize = self::getScreenSize();
            if ($screenSize === null) {
                return false;
            }
            
            // Use default window size for centering
            // These values should match the window creation parameters
            $windowWidth = 900;  // Default width from MainWindow creation
            $windowHeight = 600; // Default height from MainWindow creation
            
            // Calculate centered position
            $x = max(0, ($screenSize['width'] - $windowWidth) / 2);
            $y = max(0, ($screenSize['height'] - $windowHeight) / 2);
            
            // Set window position
            LibuiWindow::setPosition($window, (int)$x, (int)$y);
            
            return true;
        } catch (\Throwable $e) {
            // Ignore errors
            return false;
        }
    }
    
    /**
     * Get screen size
     * 
     * @return array|null Array with 'width' and 'height' keys, or null on failure
     */
    private static function getScreenSize(): ?array
    {
        $os = strtolower(PHP_OS);
        
        if (strpos($os, 'darwin') === 0) {
            // macOS
            return self::getScreenSizeMacOS();
        } elseif (strpos($os, 'win') === 0) {
            // Windows
            return self::getScreenSizeWindows();
        } elseif (strpos($os, 'linux') === 0) {
            // Linux
            return self::getScreenSizeLinux();
        }
        
        return null;
    }
    
    /**
     * Get screen size on macOS
     * 
     * @return array|null Array with 'width' and 'height' keys, or null on failure
     */
    private static function getScreenSizeMacOS(): ?array
    {
        // Try to get screen resolution using system_profiler
        $command = 'system_profiler SPDisplaysDataType | grep "Resolution" | head -1';
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            // Parse output like "Resolution: 2560 x 1440"
            if (preg_match('/(\d+)\s*x\s*(\d+)/', $output[0], $matches)) {
                return [
                    'width' => (int)$matches[1],
                    'height' => (int)$matches[2]
                ];
            }
        }
        
        // Fallback to a default size
        return [
            'width' => 1920,
            'height' => 1080
        ];
    }
    
    /**
     * Get screen size on Windows
     * 
     * @return array|null Array with 'width' and 'height' keys, or null on failure
     */
    private static function getScreenSizeWindows(): ?array
    {
        // Use PowerShell to get screen resolution
        $script = 'Add-Type -AssemblyName System.Windows.Forms; [System.Windows.Forms.Screen]::PrimaryScreen.Bounds.Size';
        $command = 'powershell -Command "' . $script . '"';
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            // Parse output like "{Width=1920, Height=1080}"
            if (preg_match('/Width=(\d+).*Height=(\d+)/', implode(' ', $output), $matches)) {
                return [
                    'width' => (int)$matches[1],
                    'height' => (int)$matches[2]
                ];
            }
        }
        
        // Fallback to a default size
        return [
            'width' => 1920,
            'height' => 1080
        ];
    }
    
    /**
     * Get screen size on Linux
     * 
     * @return array|null Array with 'width' and 'height' keys, or null on failure
     */
    private static function getScreenSizeLinux(): ?array
    {
        // Check if xrandr is available
        exec('which xrandr', $output, $returnCode);
        if ($returnCode === 0) {
            // Get screen resolution using xrandr
            $command = 'xrandr | grep "*" | head -1';
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && !empty($output)) {
                // Parse output like "1920x1080"
                if (preg_match('/(\d+)x(\d+)/', $output[0], $matches)) {
                    return [
                        'width' => (int)$matches[1],
                        'height' => (int)$matches[2]
                    ];
                }
            }
        }
        
        // Fallback to a default size
        return [
            'width' => 1920,
            'height' => 1080
        ];
    }
}