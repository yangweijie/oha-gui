# Requirements Document

## Introduction

This feature involves creating a cross-platform GUI application for HTTP load testing using the kingbes/libui PHP library. The application will provide a user-friendly interface to configure and execute oha command-line pressure tests, with the ability to save and load test configurations in JSON format. The tool must work seamlessly across Windows, macOS, and Linux operating systems.

## Requirements

### Requirement 1

**User Story:** As a developer, I want a GUI interface to configure HTTP load testing parameters, so that I can easily set up pressure tests without memorizing command-line syntax.

#### Acceptance Criteria

1. WHEN the application starts THEN the system SHALL display a main window with input fields for all oha parameters
2. WHEN I enter a URL THEN the system SHALL validate the URL format and show validation feedback
3. WHEN I set concurrent connections THEN the system SHALL accept numeric values and validate reasonable ranges
4. WHEN I configure duration THEN the system SHALL accept time values in seconds
5. WHEN I set HTTP method THEN the system SHALL provide a dropdown with GET, POST, PUT, DELETE, PATCH options
6. WHEN I add request headers THEN the system SHALL provide a key-value input interface
7. WHEN I add request body THEN the system SHALL provide a text area for JSON/form data input

### Requirement 2

**User Story:** As a user, I want to save my test configurations, so that I can reuse them for repeated testing scenarios.

#### Acceptance Criteria

1. WHEN I click "Save Configuration" THEN the system SHALL prompt for a configuration name
2. WHEN I provide a configuration name THEN the system SHALL save all current parameters to a JSON file
3. WHEN the configuration is saved THEN the system SHALL show a success confirmation message
4. WHEN I save a configuration with an existing name THEN the system SHALL ask for confirmation to overwrite
5. IF the save operation fails THEN the system SHALL display an error message with details

### Requirement 3

**User Story:** As a user, I want to select configurations from a dropdown menu, so that I can quickly switch between different test scenarios.

#### Acceptance Criteria

1. WHEN the application starts THEN the system SHALL display a configuration dropdown labeled "配置" (Configuration)
2. WHEN no configuration is selected THEN the dropdown SHALL show "Select Config" as placeholder text
3. WHEN I click the configuration dropdown THEN the system SHALL show all available saved configurations
4. WHEN I select a configuration from the dropdown THEN the system SHALL load all parameters into the form fields
5. WHEN a configuration is loaded THEN the system SHALL update the dropdown to show the selected configuration name
6. IF no configurations exist THEN the dropdown SHALL show appropriate placeholder text

### Requirement 4

**User Story:** As a user, I want to execute pressure tests using oha, so that I can analyze the performance of my HTTP endpoints.

#### Acceptance Criteria

1. WHEN I click "Start Test" THEN the system SHALL validate all required parameters are filled
2. WHEN validation passes THEN the system SHALL construct the appropriate oha command with all parameters
3. WHEN the oha command is executed THEN the system SHALL display real-time output in a results area
4. WHEN the test completes THEN the system SHALL parse and display formatted results
5. WHEN a test is running THEN the system SHALL provide a "Stop Test" button to cancel execution
6. IF oha is not installed THEN the system SHALL display an error message with installation instructions

### Requirement 5

**User Story:** As a user, I want the application to work on Windows, macOS, and Linux, so that I can use it regardless of my operating system.

#### Acceptance Criteria

1. WHEN the application runs on Windows THEN the system SHALL execute oha.exe commands correctly
2. WHEN the application runs on macOS THEN the system SHALL execute oha commands correctly
3. WHEN the application runs on Linux THEN the system SHALL execute oha commands correctly
4. WHEN detecting the operating system THEN the system SHALL use the appropriate oha binary path
5. WHEN file paths are used THEN the system SHALL handle OS-specific path separators correctly

### Requirement 6

**User Story:** As a user, I want to manage my saved configurations through a dedicated management interface, so that I can organize and maintain my test scenarios efficiently.

#### Acceptance Criteria

1. WHEN I click the "管理" (Management) button THEN the system SHALL open a configuration management popup window
2. WHEN the management window opens THEN the system SHALL display a "新增" (Add New) button at the top
3. WHEN the management window opens THEN the system SHALL display a table with columns for "名称" (Name), "配置概要" (Configuration Summary), and action buttons
4. WHEN I view the configuration table THEN each row SHALL have "编辑" (Edit), "删除" (Delete), and "选择" (Select) buttons
5. WHEN I click "新增" THEN the system SHALL open a new popup window for creating a configuration
6. WHEN I click "编辑" on a configuration THEN the system SHALL open a popup window with the configuration loaded for editing
7. WHEN I click "删除" on a configuration THEN the system SHALL show a custom confirmation dialog
8. WHEN I confirm deletion THEN the system SHALL delete both the configuration file and remove the row from the table
9. WHEN I click "选择" on a configuration THEN the system SHALL close the management window and return to the main interface
10. WHEN a configuration is selected THEN the system SHALL refresh the configuration dropdown in the main interface and select the chosen configuration

### Requirement 7

**User Story:** As a user, I want clear feedback on test results, so that I can understand the performance metrics of my endpoints.

#### Acceptance Criteria

1. WHEN a test completes THEN the system SHALL display requests per second, total requests, and success rate
2. WHEN parsing oha output THEN the system SHALL extract key metrics using appropriate regular expressions
3. WHEN displaying results THEN the system SHALL format numbers with appropriate precision and units
4. WHEN an error occurs during testing THEN the system SHALL display the error message clearly
5. WHEN results are available THEN the system SHALL provide an option to save results to a file