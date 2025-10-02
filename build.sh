#!/bin/bash

echo "Building OHA GUI Tool PHAR package..."

# Check if box is installed
if [ ! -f "vendor/bin/box" ]; then
    echo "Installing humbug/box..."
    composer require --dev humbug/box
fi

# Compile the PHAR
echo "Compiling PHAR package..."
php vendor/bin/box compile

# Check if compilation was successful
if [ -f "oha-gui.phar" ]; then
    echo "Build successful!"
    echo "PHAR file size: $(du -h oha-gui.phar | cut -f1)"
    echo "You can run the application with: php oha-gui.phar"
else
    echo "Build failed!"
    exit 1
fi