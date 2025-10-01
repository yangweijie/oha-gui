<?php

declare(strict_types=1);

namespace OhaGui\App;

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
        $options->Size = \FFI::sizeof($options);
        
        $error = $ffi->uiInit(\FFI::addr($options));
        
        if ($error !== null) {
            $errorMessage = \FFI::string($error);
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
            $this->setupPeriodicTimer();
            App::onShouldQuit(function () {
                App::quit();
                return true;
            });
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
     * Setup periodic timer for test execution monitoring
     */
    private function setupPeriodicTimer(): void
    {
        // Queue the first update
        $this->queueNextUpdate();
    }
    
    /**
     * Queue the next update callback
     */
    private function queueNextUpdate(): void
    {
        if ($this->mainWindow !== null) {
            $this->mainWindow->update();
        }
        
        // Re-queue the callback for continuous updates
        if ($this->isRunning) {
            $self = $this;
            $callback = function () use ($self) {
                // Add a small delay to prevent excessive CPU usage
                usleep(100000); // 100ms delay
                $self->queueNextUpdate();
            };
            App::queueMain($callback);
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
     * Handle application quit request
     * Called when user tries to quit the application
     * 
     * @return bool true to allow quit, false to prevent
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