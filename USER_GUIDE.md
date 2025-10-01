# OHA GUI Tool - User Guide

A cross-platform graphical user interface for the OHA HTTP load testing tool.

## Quick Start

### Prerequisites

- **PHP 8.0 or higher** with FFI extension enabled
- **Composer** for dependency management
- **OHA binary** (included in `bin/` directory or install separately)

### Installation

1. **Clone or download** this project
2. **Install dependencies**:
   ```bash
   composer install --no-dev
   ```
3. **Run the application**:
   - **Linux/macOS**: `./start.sh`
   - **Windows**: `start.bat`
   - **Direct**: `php main.php`

### System Check

Before first use, verify your system meets all requirements:

```bash
# Linux/macOS
./start.sh --check

# Windows
start.bat --check

# Direct
php main.php --check
```

## Using the Application

### Main Interface

The application window contains three main sections:

1. **Input Section** - Configure your load test parameters
2. **Results Section** - View test metrics and performance data
3. **Test Output Section** - Real-time output from the OHA tool

### Configuration Parameters

#### Required Fields

- **URL**: The target URL to test (must start with `http://` or `https://`)
- **Method**: HTTP method (GET, POST, PUT, DELETE, PATCH)
- **Connections**: Number of concurrent connections (start with 1-10)
- **Duration**: Test duration in seconds (start with 5-10 seconds)

#### Optional Fields

- **Timeout**: Request timeout in seconds (default: 30)
- **Headers**: HTTP headers in "Key: Value" format, one per line
- **Body**: Request body for POST/PUT requests (JSON format recommended)

### Managing Configurations

#### Saving Configurations

1. Fill in your test parameters
2. Click the **Save** button or use Ctrl+S
3. Enter a name for your configuration
4. Click **OK** to save

#### Loading Configurations

1. Click the **Configuration** dropdown
2. Select a saved configuration
3. All parameters will be loaded automatically

#### Managing Saved Configurations

1. Click the **管理** (Management) button
2. In the management window you can:
   - **新增** (Add New): Create a new configuration
   - **编辑** (Edit): Modify an existing configuration
   - **删除** (Delete): Remove a configuration
   - **选择** (Select): Load a configuration and return to main window

### Running Tests

1. **Configure** your test parameters
2. Click **开始** (Start) to begin the test
3. Monitor **real-time output** in the bottom section
4. View **results summary** in the middle section
5. Click **停止** (Stop) to cancel a running test

### Understanding Results

The results section displays key metrics:

- **Requests/sec**: Average requests per second
- **Total requests**: Total number of requests sent
- **Success rate**: Percentage of successful requests
- **Performance**: Additional performance metrics

## Troubleshooting

### Common Issues

#### "OHA binary not found"

**Solutions:**
1. Install OHA: `cargo install oha`
2. Download from: https://github.com/hatoo/oha/releases
3. Place `oha` (or `oha.exe` on Windows) in the `bin/` directory
4. Ensure OHA is in your system PATH

#### "FFI extension not enabled"

**Solutions:**
1. Enable in `php.ini`: `extension=ffi`
2. Restart your PHP process
3. Check with: `php -m | grep ffi`

#### "libui library not found"

**Solutions:**
1. Run: `composer install`
2. Ensure all dependencies are installed
3. Check that `vendor/` directory exists

#### "Permission denied" errors

**Solutions:**
1. Check file permissions in configuration directory
2. On Unix systems: `chmod 755 ~/.config/oha-gui`
3. Ensure write access to application directory

### Getting Help

1. **Built-in help**: Press F1 in the application (if supported)
2. **Command line help**: `php main.php --help`
3. **System check**: `php main.php --check`
4. **Version info**: `php main.php --version`

## Configuration Files

Configurations are stored in:

- **Windows**: `%APPDATA%\OhaGui\`
- **macOS**: `~/Library/Application Support/OhaGui/`
- **Linux**: `~/.config/oha-gui/`

Each configuration is saved as a JSON file with the `.json` extension.

## Advanced Usage

### Command Line Options

```bash
php main.php [options]

Options:
  --help, -h     Show help message
  --version, -v  Show version information
  --check        Check system requirements
```

### Custom OHA Installation

If you have OHA installed in a custom location:

1. Ensure it's in your system PATH, or
2. Place the binary in the `bin/` directory, or
3. Create a symlink in the `bin/` directory

### Performance Tips

1. **Start small**: Begin with low concurrency (1-5 connections)
2. **Short tests**: Use 5-10 second durations for initial testing
3. **Monitor resources**: Watch CPU and memory usage during tests
4. **Network considerations**: Be mindful of network bandwidth limits
5. **Target server**: Ensure the target can handle the load

### Best Practices

1. **Test responsibly**: Don't overload servers you don't own
2. **Start gradually**: Increase load incrementally
3. **Monitor results**: Watch for error rates and response times
4. **Save configurations**: Create reusable test scenarios
5. **Document tests**: Keep notes about test purposes and results

## Keyboard Shortcuts

- **Ctrl+N** (Cmd+N on Mac): New configuration
- **Ctrl+S** (Cmd+S on Mac): Save configuration
- **Ctrl+O** (Cmd+O on Mac): Open configuration manager
- **F5**: Start test
- **Escape**: Stop test
- **Ctrl+Q** (Cmd+Q on Mac): Quit application

*Note: Keyboard shortcuts availability depends on GUI library capabilities.*

## Platform-Specific Notes

### Windows

- Use `start.bat` for easy launching
- Ensure `oha.exe` is available
- FFI extension may need manual enabling

### macOS

- Use `./start.sh` for easy launching
- Ensure `oha` binary has execute permissions
- May need to allow the application in Security preferences

### Linux

- Use `./start.sh` for easy launching
- Install OHA via package manager or Cargo
- Ensure FFI extension is installed: `sudo apt-get install php-ffi`

## Support

For issues and questions:

1. Check this user guide
2. Run system diagnostics: `php main.php --check`
3. Review error messages for specific guidance
4. Check the OHA project: https://github.com/hatoo/oha

## Version Information

- **Application**: OHA GUI Tool v1.0.0
- **OHA Compatibility**: Tested with OHA 1.10.0+
- **PHP Requirements**: 8.0+
- **Platforms**: Windows, macOS, Linux