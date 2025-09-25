@echo off
REM OHA GUI Tool - Windows Launcher
REM This batch file launches the OHA GUI application on Windows

echo Starting OHA GUI Tool...

REM Check if PHP is available
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: PHP is not installed or not in PATH
    echo Please install PHP and add it to your PATH environment variable
    pause
    exit /b 1
)

REM Check if composer dependencies are installed
if not exist "vendor\autoload.php" (
    echo Installing dependencies...
    composer install
    if %errorlevel% neq 0 (
        echo Error: Failed to install dependencies
        echo Please make sure Composer is installed and run 'composer install'
        pause
        exit /b 1
    )
)

REM Run the application
php main.php

REM Pause to see any error messages
if %errorlevel% neq 0 (
    echo.
    echo Application exited with error code %errorlevel%
    pause
)