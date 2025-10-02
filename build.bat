@echo off
setlocal

echo Building OHA GUI Tool PHAR package...

REM Check if box is installed
if not exist "vendor\bin\box" (
    echo Installing humbug/box...
    composer require --dev humbug/box
)

REM Compile the PHAR
echo Compiling PHAR package...
php vendor\bin\box compile

REM Check if compilation was successful
if exist "oha-gui.phar" (
    echo Build successful!
    echo PHAR file created: oha-gui.phar
    echo You can run the application with: php oha-gui.phar
) else (
    echo Build failed!
    exit /b 1
)

endlocal