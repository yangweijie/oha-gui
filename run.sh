#!/bin/bash

# OHA GUI Tool - Unix/Linux/macOS Launcher
# This shell script launches the OHA GUI application on Unix-like systems

echo "Starting OHA GUI Tool..."

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo "Error: PHP is not installed or not in PATH"
    echo "Please install PHP and add it to your PATH environment variable"
    exit 1
fi

# Check if composer dependencies are installed
if [ ! -f "vendor/autoload.php" ]; then
    echo "Installing dependencies..."
    if command -v composer &> /dev/null; then
        composer install
        if [ $? -ne 0 ]; then
            echo "Error: Failed to install dependencies"
            echo "Please make sure Composer is installed and run 'composer install'"
            exit 1
        fi
    else
        echo "Error: Composer is not installed"
        echo "Please install Composer and run 'composer install'"
        exit 1
    fi
fi

# Run the application
php main.php

# Check exit code
if [ $? -ne 0 ]; then
    echo ""
    echo "Application exited with an error"
    read -p "Press Enter to continue..."
fi