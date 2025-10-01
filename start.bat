@echo off
REM OHA GUI Tool Startup Script for Windows
REM This script provides a convenient way to start the OHA GUI application

setlocal enabledelayedexpansion

echo ================================
echo     OHA GUI Tool Launcher
echo ================================
echo.

REM Check if we're in the right directory
if not exist "main.php" (
    echo [ERROR] main.php not found
    echo Please run this script from the OHA GUI project directory
    pause
    exit /b 1
)

if not exist "composer.json" (
    echo [ERROR] composer.json not found
    echo Please run this script from the OHA GUI project directory
    pause
    exit /b 1
)

REM Check PHP installation
php --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] PHP is not installed or not in PATH
    echo Please install PHP 8.0 or higher and add it to your PATH
    pause
    exit /b 1
)

echo [INFO] PHP is available

REM Check PHP version
for /f "tokens=2 delims= " %%i in ('php -r "echo PHP_VERSION;"') do set PHP_VERSION=%%i
echo [INFO] PHP version: %PHP_VERSION%

REM Check dependencies
if not exist "vendor" (
    echo [WARN] Dependencies not installed. Checking for Composer...
    composer --version >nul 2>&1
    if errorlevel 1 (
        echo [ERROR] Composer is not installed
        echo Please install Composer and run: composer install
        pause
        exit /b 1
    )
    echo [INFO] Installing dependencies...
    composer install --no-dev --optimize-autoloader
    if errorlevel 1 (
        echo [ERROR] Failed to install dependencies
        pause
        exit /b 1
    )
) else (
    echo [INFO] Dependencies are installed
)

REM Handle command line arguments
if "%1"=="--help" goto :help
if "%1"=="-h" goto :help
if "%1"=="--version" goto :version
if "%1"=="--check" goto :check

REM Check system requirements
echo [INFO] Checking system requirements...
php main.php --check
if errorlevel 1 (
    echo [ERROR] System requirements check failed
    pause
    exit /b 1
)

echo.
echo [INFO] Launching application...
echo.

REM Start the application
php main.php %*
goto :end

:help
echo OHA GUI Tool Launcher for Windows
echo.
echo Usage: %0 [options]
echo.
echo Options:
echo   --help, -h     Show this help message
echo   --check        Check system requirements only
echo   --version      Show version information
echo.
echo This script will:
echo   1. Check PHP version and requirements
echo   2. Install dependencies if needed
echo   3. Verify system requirements
echo   4. Launch the OHA GUI application
echo.
goto :end

:version
php main.php --version
goto :end

:check
echo [INFO] Checking system requirements only...
php main.php --check
goto :end

:end
if "%1"=="" pause
endlocal