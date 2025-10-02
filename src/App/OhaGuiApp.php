<?php

declare(strict_types=1);

namespace OhaGui\App;

use FFI;
use Kingbes\Libui\App;
use Kingbes\Libui\Base;
use OhaGui\GUI\MainWindow;
use RuntimeException;
use Throwable;

/**
 * Main application class for OHA GUI Tool
 * Handles libui initialization, application lifecycle, and main event loop
 */
class OhaGuiApp extends Base
{
    private ?MainWindow $mainWindow = null;
    private bool $isRunning = false;

    /**
     * Initialize the application
     */
    public function __construct()
    {
        // Initialize libui
        $this->initializeLibui();
    }

    /**
     * Initialize libui library
     * 
     * @throws RuntimeException if libui initialization fails
     */
    private function initializeLibui(): void
    {
        $ffi = self::ffi();
        
        // Initialize libui
        $options = $ffi->new('uiInitOptions');
        $options->Size = FFI::sizeof($options);
        
        $error = $ffi->uiInit(FFI::addr($options));
        
        if ($error !== null) {
            $errorMessage = FFI::string($error);
            $ffi->uiFreeInitError($error);
            throw new RuntimeException("Failed to initialize libui: " . $errorMessage);
        }
    }

    /**
     * Run the application main loop
     *
     * @return void
     * @throws Throwable
     */
    public function run(): void
    {
        if ($this->isRunning) {
            return;
        }

        $this->isRunning = true;

        try {
            // Create and show main window
            $this->mainWindow = new MainWindow();
            $this->mainWindow->show();

            // Set up periodic timer for test execution monitoring
            App::onShouldQuit(function () {
                return $this->onShouldQuit();
            });
            
            // Set up a timer to periodically update the test executor
            $this->setupTestExecutorTimer();

            // Start the main event loop
            App::main();

        } catch (Throwable $e) {
            error_log("Application error: " . $e->getMessage());
            throw $e;
        } finally {
            $this->shutdown();
        }
    }

    /**
     * Shutdown the application and cleanup resources
     * 
     * @return void
     */
    public function shutdown(): void
    {
        if (!$this->isRunning) {
            return;
        }

        $this->isRunning = false;

        try {
            // Cleanup main window first
            if ($this->mainWindow !== null) {
                $this->mainWindow->cleanup();
                $this->mainWindow = null;
            }

            // Quit libui main loop
            App::quit();

            // Log successful shutdown
            error_log("OHA GUI application shutdown completed successfully");

        } catch (Throwable $e) {
            error_log("Shutdown error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Get the main window instance
     * 
     * @return MainWindow|null
     */
    public function getMainWindow(): ?MainWindow
    {
        return $this->mainWindow;
    }

    /**
     * Check if the application is running
     * 
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    /**
     * Set up a timer to periodically update the test executor
     */
    private function setupTestExecutorTimer(): void
    {
        // Create a repeating timer
        $this->createRepeatingTimer(100);
    }
    
    /**
     * Create a repeating timer
     */
    private function createRepeatingTimer(int $milliseconds): void
    {
        App::timer($milliseconds, function () use ($milliseconds) {
            if ($this->mainWindow !== null && $this->isRunning) {
                $this->mainWindow->updateTestExecutor();
            }
            // Create the next timer if the app is still running
            if ($this->isRunning) {
                $this->createRepeatingTimer($milliseconds);
            }
            return false; // Don't continue this timer instance
        });
    }

    /**
     * Update the test executor periodically
     * This method is called by the libui event loop to monitor test execution
     */
    private function updateTestExecutor(): void
    {
        // If we have a main window, update its test executor
        $this->mainWindow?->updateTestExecutor();
    }

    /**
     * Handle application quit request
     * Called when user tries to quit the application
     * 
     * @return bool true to allow to quit, false to prevent
     */
    public function onShouldQuit(): bool
    {
        App::quit();
        return true;
    }

    /**
     * Destructor - ensure cleanup
     */
    public function __destruct()
    {
        if ($this->isRunning) {
            $this->shutdown();
        }
    }
}