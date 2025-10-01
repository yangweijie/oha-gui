#!/bin/bash

# OHA GUI Tool Startup Script
# This script provides a convenient way to start the OHA GUI application

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${BLUE}================================${NC}"
    echo -e "${BLUE}    OHA GUI Tool Launcher${NC}"
    echo -e "${BLUE}================================${NC}"
    echo
}

# Check if we're in the right directory
check_directory() {
    if [ ! -f "main.php" ] || [ ! -f "composer.json" ]; then
        print_error "Please run this script from the OHA GUI project directory"
        print_error "Expected files: main.php, composer.json"
        exit 1
    fi
}

# Check PHP version
check_php() {
    if ! command -v php &> /dev/null; then
        print_error "PHP is not installed or not in PATH"
        exit 1
    fi
    
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    print_status "PHP version: $PHP_VERSION"
    
    # Check if PHP version is >= 8.0
    if ! php -r "exit(version_compare(PHP_VERSION, '8.0.0', '>=') ? 0 : 1);"; then
        print_error "PHP 8.0.0 or higher is required"
        exit 1
    fi
}

# Check and install dependencies
check_dependencies() {
    if [ ! -d "vendor" ]; then
        print_warning "Dependencies not installed. Running composer install..."
        if command -v composer &> /dev/null; then
            composer install --no-dev --optimize-autoloader
        else
            print_error "Composer is not installed. Please install dependencies manually."
            exit 1
        fi
    else
        print_status "Dependencies are installed"
    fi
}

# Check system requirements
check_requirements() {
    print_status "Checking system requirements..."
    if php main.php --check; then
        print_status "All requirements satisfied"
    else
        print_error "System requirements check failed"
        exit 1
    fi
}

# Main function
main() {
    print_header
    
    print_status "Starting OHA GUI Tool..."
    
    check_directory
    check_php
    check_dependencies
    check_requirements
    
    echo
    print_status "Launching application..."
    echo
    
    # Start the application
    php main.php "$@"
}

# Handle script arguments
case "${1:-}" in
    --help|-h)
        echo "OHA GUI Tool Launcher"
        echo
        echo "Usage: $0 [options]"
        echo
        echo "Options:"
        echo "  --help, -h     Show this help message"
        echo "  --check        Check system requirements only"
        echo "  --version      Show version information"
        echo
        echo "This script will:"
        echo "  1. Check PHP version and requirements"
        echo "  2. Install dependencies if needed"
        echo "  3. Verify system requirements"
        echo "  4. Launch the OHA GUI application"
        echo
        exit 0
        ;;
    --version)
        php main.php --version
        exit 0
        ;;
    --check)
        check_directory
        check_php
        check_dependencies
        check_requirements
        exit 0
        ;;
    *)
        main "$@"
        ;;
esac