#!/bin/bash

echo "Building OHA GUI Tool PHAR package..."

# Check if we're on Windows (using WSL or Git Bash)
if [[ "$OSTYPE" == "msys" || "$OSTYPE" == "win32" || "$OSTYPE" == "cygwin" ]]; then
    # Use Windows-style paths and commands
    BOX_PATH="vendor\\bin\\box"
    PHP_CMD="php"
else
    # Use Unix-style paths and commands
    BOX_PATH="vendor/bin/box"
    PHP_CMD="php"
fi

# Check if box is installed
if [ ! -f "$BOX_PATH" ] && [ ! -f "vendor/bin/box" ]; then
    echo "Installing humbug/box..."
    composer require --dev humbug/box
fi

# Compile the PHAR
echo "Compiling PHAR package..."
$PHP_CMD $BOX_PATH compile

# Check if compilation was successful
if [ -f "oha-gui.phar" ]; then
    echo "Build successful!"
    echo "PHAR file size: $(du -h oha-gui.phar | cut -f1)"
    echo "You can run the application with: php oha-gui.phar"
else
    echo "Build failed!"
    exit 1
fi