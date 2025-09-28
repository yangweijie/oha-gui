<?php

namespace OhaGui\App;

use Kingbes\Libui\App;
use OhaGui\GUI\MainWindow;
use RuntimeException;
use Throwable;

/**
 * Main OHA GUI Application class
 * 
 * Handles application initialization, lifecycle management, and main event loop
 */
class OhaGuiApp
{
    private ?MainWindow $mainWindow = null;
    private bool $isRunning = false;

    /**
     * Initialize the application
     */
    public function __construct()
    {
        try {
            // Initialize libui
            App::init();
            
            // Set up application quit handler
            App::onShouldQuit(function($data) {
                return $this->onShouldQuit();
            });
            
            $this->isRunning = true;
            
            // Log successful initialization
            error_log("OHA GUI Application initialized successfully");
            
        } catch (Throwable $e) {
            error_log("Failed to initialize OHA GUI Application: " . $e->getMessage());
            throw new RuntimeException("Application initialization failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Run the main application
     * 
     * @return void
     */
    public function run(): void
    {
        if (!$this->isRunning) {
            throw new RuntimeException('Application is not initialized or has been shut down');
        }

        // Create and show the main window
        $this->mainWindow = new MainWindow();
        $this->mainWindow->show();

        // Set up periodic timer for test status updates
        $this->setupPeriodicUpdates();

        // Start the main event loop
        App::main();
    }

    /**
     * Setup periodic updates for test execution monitoring
     * 
     * @return void
     */
    private function setupPeriodicUpdates(): void
    {
        // Note: libui doesn't have built-in timer support
        // The test execution monitoring is handled through callbacks
        // in the TestExecutor, so no additional periodic updates are needed
        // This method is kept for future enhancements if needed
    }

    /**
     * Shutdown the application with comprehensive cleanup
     * 
     * @return void
     */
    public function shutdown(): void
    {
        if ($this->isRunning) {
            error_log("Shutting down OHA GUI Application...");
            
            $this->isRunning = false;
            
            try {
                // Perform comprehensive cleanup if main window exists
                if ($this->mainWindow !== null) {
                    $this->mainWindow->performResourceCleanup();
                    $this->mainWindow = null;
                }
                
                // Quit the main loop
                App::quit();
                
                // Uninitialize libui
                App::unInit();
                
                error_log("OHA GUI Application shutdown completed successfully");
                
            } catch (Throwable $e) {
                error_log("Error during application shutdown: " . $e->getMessage());
                // Continue with shutdown even if cleanup fails
                try {
                    App::quit();
                    App::unInit();
                } catch (Throwable $cleanupError) {
                    error_log("Critical error during final cleanup: " . $cleanupError->getMessage());
                }
            }
        }
    }

    /**
     * Handle application quit request
     * 
     * @return int 1 to allow quit, 0 to prevent quit
     */
    private function onShouldQuit(): int
    {
        // Allow the application to quit
        $this->shutdown();
        return 1;
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
}