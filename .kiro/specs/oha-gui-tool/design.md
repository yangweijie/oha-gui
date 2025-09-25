# Design Document

## Overview

The OHA GUI Tool is a cross-platform desktop application built with PHP and the kingbes/libui library. It provides a user-friendly graphical interface for configuring and executing HTTP load tests using the oha command-line tool. The application features configuration management, real-time test execution, and result visualization.

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    OHA GUI Application                      │
├─────────────────────────────────────────────────────────────┤
│  GUI Layer (kingbes/libui)                                 │
│  ├── Main Window                                           │
│  ├── Configuration Form                                     │
│  ├── Results Display                                        │
│  └── Configuration Management                               │
├─────────────────────────────────────────────────────────────┤
│  Business Logic Layer                                       │
│  ├── OHA Command Builder                                    │
│  ├── Configuration Manager                                  │
│  ├── Test Executor                                          │
│  └── Result Parser                                          │
├─────────────────────────────────────────────────────────────┤
│  Data Layer                                                 │
│  ├── JSON Configuration Files                               │
│  └── Cross-Platform File System                             │
├─────────────────────────────────────────────────────────────┤
│  External Dependencies                                       │
│  ├── OHA Binary (Windows/macOS/Linux)                      │
│  └── System Process Execution                               │
└─────────────────────────────────────────────────────────────┘
```

### Directory Structure

```
src/
├── App/
│   └── OhaGuiApp.php              # Main application class
├── GUI/
│   ├── MainWindow.php             # Main window implementation
│   ├── ConfigurationForm.php      # Test configuration form
│   ├── ResultsDisplay.php         # Test results display
│   └── ConfigurationList.php      # Configuration management UI
├── Core/
│   ├── OhaCommandBuilder.php      # OHA command construction
│   ├── ConfigurationManager.php   # Configuration CRUD operations
│   ├── TestExecutor.php           # Test execution and monitoring
│   └── ResultParser.php           # OHA output parsing
├── Models/
│   ├── TestConfiguration.php      # Test configuration data model
│   └── TestResult.php             # Test result data model
└── Utils/
    ├── CrossPlatform.php          # Cross-platform utilities
    └── FileManager.php            # File system operations
```

## Components and Interfaces

### 1. Main Application (OhaGuiApp)

**Responsibilities:**
- Initialize the libui application
- Create and manage the main window
- Handle application lifecycle events

**Key Methods:**
```php
class OhaGuiApp
{
    public function __construct()
    public function run(): void
    public function shutdown(): void
}
```

### 2. Main Window (MainWindow)

**Responsibilities:**
- Create the main GUI layout
- Coordinate between different UI components
- Handle window events

**Key Methods:**
```php
class MainWindow
{
    public function __construct()
    public function createLayout(): void
    public function show(): void
    public function onClosing(): bool
}
```

### 3. Configuration Form (ConfigurationForm)

**Responsibilities:**
- Display input fields for test parameters
- Validate user input
- Trigger test execution

**Key Fields:**
- URL (Entry)
- HTTP Method (Combobox)
- Concurrent Connections (Spinbox)
- Duration (Spinbox)
- Timeout (Spinbox)
- Request Headers (MultilineEntry)
- Request Body (MultilineEntry)

**Key Methods:**
```php
class ConfigurationForm
{
    public function createForm(): object
    public function getConfiguration(): TestConfiguration
    public function setConfiguration(TestConfiguration $config): void
    public function validateInput(): array
    public function onStartTest(): void
}
```

### 4. OHA Command Builder (OhaCommandBuilder)

**Responsibilities:**
- Convert configuration to oha command
- Handle cross-platform binary paths
- Escape command arguments properly

**Key Methods:**
```php
class OhaCommandBuilder
{
    public function buildCommand(TestConfiguration $config): string
    private function getOhaBinaryPath(): string
    private function escapeArgument(string $arg): string
}
```

Based on tech.md, the command structure follows:
```bash
oha -c {concurrent_count} -z {duration}s -t {timeout}s -m {method} --no-tui -H "header: value" -d 'body' "url"
```

### 5. Configuration Manager (ConfigurationManager)

**Responsibilities:**
- Save/load configurations to/from JSON files
- Manage configuration list
- Handle configuration CRUD operations

**Key Methods:**
```php
class ConfigurationManager
{
    public function saveConfiguration(string $name, TestConfiguration $config): bool
    public function loadConfiguration(string $name): ?TestConfiguration
    public function listConfigurations(): array
    public function deleteConfiguration(string $name): bool
    private function getConfigPath(string $name): string
}
```

### 6. Test Executor (TestExecutor)

**Responsibilities:**
- Execute oha commands asynchronously
- Monitor test progress
- Handle process termination

**Key Methods:**
```php
class TestExecutor
{
    public function executeTest(string $command, callable $outputCallback): void
    public function stopTest(): void
    public function isRunning(): bool
}
```

### 7. Result Parser (ResultParser)

**Responsibilities:**
- Parse oha output using regex patterns
- Extract key metrics
- Format results for display

**Key Methods:**
```php
class ResultParser
{
    public function parseOutput(string $output): TestResult
    private function extractMetrics(string $output): array
}
```

Based on tech.md, the parsing patterns are:
```php
preg_match_all('/Success rate:\s*(\d+\.\d+)%|Total:\s*(\d+)\s+requests|Requests\/sec:\s*(\d+\.\d+)/im', $output, $matches);
```

## Data Models

### TestConfiguration

```php
class TestConfiguration
{
    public string $name;
    public string $url;
    public string $method;
    public int $concurrentConnections;
    public int $duration;
    public int $timeout;
    public array $headers;
    public string $body;
    public DateTime $createdAt;
    public DateTime $updatedAt;
    
    public function toArray(): array
    public static function fromArray(array $data): self
    public function validate(): array
}
```

### TestResult

```php
class TestResult
{
    public float $requestsPerSecond;
    public int $totalRequests;
    public int $failedRequests;
    public float $successRate;
    public string $rawOutput;
    public DateTime $executedAt;
    
    public function toArray(): array
    public function getFormattedSummary(): string
}
```

## Error Handling

### Input Validation
- URL format validation using filter_var()
- Numeric range validation for connections, duration, timeout
- JSON validation for request body when applicable

### Process Execution Errors
- OHA binary not found
- Command execution failures
- Process timeout handling
- Invalid command arguments

### File System Errors
- Configuration file read/write permissions
- Invalid JSON format in configuration files
- Disk space issues

### Cross-Platform Considerations
- Path separator handling (Windows vs Unix)
- Binary executable extensions (.exe on Windows)
- Process execution differences

## Testing Strategy

### Unit Testing
- Test configuration validation logic
- Test command building with various parameters
- Test result parsing with sample oha outputs
- Test configuration serialization/deserialization

### Integration Testing
- Test GUI component interactions
- Test file system operations across platforms
- Test process execution with mock oha commands

### Cross-Platform Testing
- Test on Windows, macOS, and Linux
- Verify binary path detection
- Test file path handling
- Verify GUI rendering consistency

### Manual Testing Scenarios
1. Create and save a new configuration
2. Load and modify an existing configuration
3. Execute a test with various parameters
4. Handle test interruption
5. Parse and display different oha output formats
6. Test error scenarios (invalid URL, missing binary, etc.)

## Performance Considerations

### GUI Responsiveness
- Use non-blocking process execution
- Update GUI with periodic callbacks during test execution
- Implement proper event handling to prevent UI freezing

### Memory Management
- Limit output buffer size for long-running tests
- Clean up process resources properly
- Avoid memory leaks in GUI event handlers

### File I/O Optimization
- Cache configuration list to avoid repeated file system access
- Use atomic file operations for configuration saves
- Implement proper file locking if needed

## Security Considerations

### Input Sanitization
- Escape shell command arguments to prevent injection
- Validate file paths to prevent directory traversal
- Sanitize configuration names to prevent file system issues

### Process Execution
- Use proper process isolation
- Limit command execution privileges
- Validate oha binary integrity if possible

### Configuration Storage
- Store configurations in user-specific directories
- Use appropriate file permissions
- Consider encryption for sensitive test data if needed