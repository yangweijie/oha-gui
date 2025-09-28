<?php

/**
 * OHA GUI Tool - Visual Main Interface Prototype
 *
 * This is a prototype implementation of the main interface shown in Image #1
 * using nested Box and Group layouts to create a visual design.
 */

declare(strict_types=1);

// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define application constants
define('APP_NAME', 'OHA GUI Tool');
define('APP_VERSION', '1.0.0');
define('APP_ROOT', __DIR__);

// Load composer autoloader
require_once APP_ROOT . '/vendor/autoload.php';

// Import required classes
use Kingbes\Libui\App;
use Kingbes\Libui\Window;
use Kingbes\Libui\Control;
use Kingbes\Libui\Box;
use Kingbes\Libui\Group;
use Kingbes\Libui\Label;
use Kingbes\Libui\Button;
use Kingbes\Libui\EditableCombobox;
use Kingbes\Libui\Separator;

try {
    // Initialize libui
    App::init();

    // Create main window
    $window = Window::create('OHA GUI Tool', 600, 400, 0);
    Window::setMargined($window, true);

    // Handle window closing
    Window::onClosing($window, function($window) {
        App::quit();
        return 1;
    });

    // Create main vertical box for layout (as shown in Image #1)
    $mainBox = Box::newVerticalBox();
    Box::setPadded($mainBox, true);

    // First horizontal box - contains input group and results group
    $topHorizontalBox = Box::newHorizontalBox();
    Box::setPadded($topHorizontalBox, true);

    // Input group (left side)
    $inputGroup = Group::create('输入');
    $inputBox = Box::newVerticalBox();
    Box::setPadded($inputBox, true);

    // Configuration label and combobox in horizontal box
    $configRow = Box::newHorizontalBox();
    Box::setPadded($configRow, true);
    
    $configLabel = Label::create('配置');
    Box::append($configRow, $configLabel, false);
    
    $configCombo = EditableCombobox::create();
    EditableCombobox::append($configCombo, 'Default Config');
    EditableCombobox::append($configCombo, 'High Load Test');
    EditableCombobox::append($configCombo, 'Stress Test');
    EditableCombobox::setText($configCombo, 'Select Configuration...');
    Box::append($configRow, $configCombo, true);
    
    $manageButton = Button::create('管理');
    Box::append($configRow, $manageButton, false);
    
    Box::append($inputBox, $configRow, false);

    // Buttons horizontal box
    $buttonsBox = Box::newHorizontalBox();
    Box::setPadded($buttonsBox, true);

    $startButton = Button::create('开始');
    Box::append($buttonsBox, $startButton, true);

    $stopButton = Button::create('停止');
    Box::append($buttonsBox, $stopButton, true);

    Box::append($inputBox, $buttonsBox, false);

    // Add event handler for manage button
    Button::onClicked($manageButton, function() use ($window) {
        // Create a new window for managing configurations
        $configWindow = Window::create('Manage Configurations', 800, 600, 0);
        Window::setMargined($configWindow, true);
        
        // Handle window closing
        Window::onClosing($configWindow, function($configWindow) {
            App::quit();
            return 1;
        });
        
        // Create main vertical box for layout
        $configMainBox = Box::newVerticalBox();
        Box::setPadded($configMainBox, true);
        
        // Create form for adding/editing configurations
        $formBox = Box::newVerticalBox();
        Box::setPadded($formBox, true);
        
        // Configuration name field
        $configNameLabel = Label::create('Configuration Name:');
        Box::append($formBox, $configNameLabel, false);
        $configNameEntry = Entry::create();
        Box::append($formBox, $configNameEntry, false);
        
        // Save button
        $saveConfigButton = Button::create('Save Configuration');
        Box::append($formBox, $saveConfigButton, false);
        
        // Add separator
        Box::append($formBox, Separator::createHorizontal(), false);
        
        // Search field
        $searchEntry = Entry::create();
        Entry::setText($searchEntry, '');
        $searchBtn = Button::create('Search');
        $searchBox = Box::newHorizontalBox();
        Box::setPadded($searchBox, true);
        Box::append($searchBox, $searchEntry, true);
        Box::append($searchBox, $searchBtn, false);
        Box::append($formBox, $searchBox, false);
        
        // Add separator
        Box::append($formBox, Separator::createHorizontal(), false);
        
        // Sample data for table
        $configurations = [
            ['Default Config', 'https://example.com', '10', '30s', '5s'],
            ['High Load Test', 'https://api.example.com', '100', '60s', '10s'],
            ['Stress Test', 'https://stress.example.com', '500', '120s', '15s']
        ];
        $filteredConfigurations = $configurations;
        
        // Table model handler
        $getTableModelHandler = function() use (&$filteredConfigurations) {
            return Table::modelHandler(
                5,
                TableValueType::String,
                count($filteredConfigurations),
                function ($handler, $row, $column) use (&$filteredConfigurations) {
                    return Table::createValueStr($filteredConfigurations[$row][$column]);
                },
                function ($handler, $row, $column, $v) use (&$filteredConfigurations) {
                    // Can be extended for editing functionality
                }
            );
        };
        
        $tableModel = Table::createModel($getTableModelHandler());
        $table = Table::create($tableModel, -1);
        Table::appendTextColumn($table, 'Name', 0, false);
        Table::appendTextColumn($table, 'URL', 1, false);
        Table::appendTextColumn($table, 'Concurrent Connections', 2, false);
        Table::appendTextColumn($table, 'Duration', 3, false);
        Table::appendTextColumn($table, 'Timeout', 4, false);
        Box::append($formBox, $table, true);
        
        // Save configuration event handler
        Button::onClicked($saveConfigButton, function () use (&$configurations, &$filteredConfigurations, $configNameEntry, $table, $getTableModelHandler, $tableModel) {
            $name = trim(Entry::text($configNameEntry));
            if (!empty($name)) {
                // For simplicity, we're not collecting all config details here
                // In a real implementation, you would collect all configuration parameters
                $newConfig = [$name, 'http://example.com', '10', '30s', '5s'];
                $configurations[] = $newConfig;
                $filteredConfigurations = $configurations;
                Table::modelRowInserted($tableModel, count($filteredConfigurations)-1);
                Entry::setText($configNameEntry, '');
            }
        });
        
        // Search filter event handler
        Button::onClicked($searchBtn, function () use (&$configurations, &$filteredConfigurations, $searchEntry, $table, $getTableModelHandler, $tableModel) {
            $keyword = trim(Entry::text($searchEntry));
            $filteredConfigurations = [];
            if ($keyword === '') {
                $filteredConfigurations = $configurations;
            } else {
                foreach ($configurations as $row) {
                    $found = false;
                    foreach ($row as $cell) {
                        if (strpos($cell, $keyword) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if ($found) {
                        $filteredConfigurations[] = $row;
                    }
                }
            }
            // Clear and rebuild table content
            for ($i=0; $i < count($configurations); $i++) { 
                Table::modelRowDeleted($tableModel, count($configurations) -($i+1));
            }
            $configurations = $filteredConfigurations;
            foreach ($filteredConfigurations as $i => $row) {
                Table::modelRowInserted($tableModel, $i);
            }
        });
        
        // Set content for config window
        Window::setChild($configWindow, $formBox);
        
        // Show the config window
        Control::show($configWindow);
        
        // Run the main event loop for the config window
        App::main();
    });

    // Add event handler for manage button
    Button::onClicked($manageButton, function() use ($window) {
        // Create a new window for managing configurations
        $configWindow = Window::create('Manage Configurations', 600, 400, 0);
        Window::setMargined($configWindow, true);
        
        // Handle window closing
        Window::onClosing($configWindow, function($configWindow) {
            App::quit();
            return 1;
        });
        
        // Create main vertical box for layout
        $configMainBox = Box::newVerticalBox();
        Box::setPadded($configMainBox, true);
        
        // Create form for adding/editing configurations
        $formBox = Box::newVerticalBox();
        Box::setPadded($formBox, true);
        
        // Configuration name field
        $configNameLabel = Label::create('Configuration Name:');
        Box::append($formBox, $configNameLabel, false);
        
        $configNameEntry = Entry::create();
        Box::append($formBox, $configNameEntry, false);
        
        // Save button
        $saveButton = Button::create('Save Configuration');
        Box::append($formBox, $saveButton, false);
        
        // Add separator
        Box::append($formBox, Separator::createHorizontal(), false);
        
        // Search field
        $searchEntry = Entry::create();
        Entry::setText($searchEntry, '');
        $searchBtn = Button::create('Search');
        $searchBox = Box::newHorizontalBox();
        Box::setPadded($searchBox, true);
        Box::append($searchBox, $searchEntry, true);
        Box::append($searchBox, $searchBtn, false);
        Box::append($formBox, $searchBox, false);
        
        // Add separator
        Box::append($formBox, Separator::createHorizontal(), false);
        
        // Table for displaying configurations
        $tableModel = Table::createModel(function() use (&$filteredConfigs) {
            return Table::modelHandler(
                1,
                TableValueType::String,
                count($filteredConfigs),
                function ($handler, $row, $column) use (&$filteredConfigs) {
                    return Table::createValueStr($filteredConfigs[$row][$column]);
                },
                function ($handler, $row, $column, $v) use (&$filteredConfigs) {
                    // Can extend for editing functionality
                }
            );
        });
        
        $table = Table::create($tableModel, -1);
        Table::appendTextColumn($table, 'Configuration Name', 0, false);
        Box::append($formBox, $table, true);
        
        // Add form to main box
        Box::append($configMainBox, $formBox, true);
        
        // Set window content
        Window::setChild($configWindow, $configMainBox);
        
        // Show window
        Control::show($configWindow);
        
        // Run the main event loop for this window
        App::main();
    });

    // Add event handler for manage button
    Button::onClicked($manageButton, function() use ($window) {
        // Create a new window for managing configurations
        $configWindow = Window::create('Manage Configurations', 600, 400, 0);
        Window::setMargined($configWindow, true);
        
        // Handle window closing
        Window::onClosing($configWindow, function($configWindow) {
            App::quit();
            return 1;
        });
        
        // Create main vertical box for layout
        $configMainBox = Box::newVerticalBox();
        Box::setPadded($configMainBox, true);
        
        // Form section for adding/editing configurations
        $formBox = Box::newVerticalBox();
        Box::setPadded($formBox, true);
        
        // Name field
        $nameLabel = Label::create('Name:');
        Box::append($formBox, $nameLabel, false);
        $nameEntry = Entry::create();
        Box::append($formBox, $nameEntry, false);
        
        // Email field
        $emailLabel = Label::create('Email:');
        Box::append($formBox, $emailLabel, false);
        $emailEntry = Entry::create();
        Box::append($formBox, $emailEntry, false);
        
        // Phone field
        $phoneLabel = Label::create('Phone:');
        Box::append($formBox, $phoneLabel, false);
        $phoneEntry = Entry::create();
        Box::append($formBox, $phoneEntry, false);
        
        // City field
        $cityLabel = Label::create('City:');
        Box::append($formBox, $cityLabel, false);
        $cityEntry = Entry::create();
        Box::append($formBox, $cityEntry, false);
        
        // State field
        $stateLabel = Label::create('State:');
        Box::append($formBox, $stateLabel, false);
        $stateEntry = Entry::create();
        Box::append($formBox, $stateEntry, false);
        
        // Save button
        $saveBtn = Button::create('Save Contact');
        Box::append($formBox, $saveBtn, false);
        
        // Separator
        Box::append($formBox, Separator::createHorizontal(), false);
        
        // Search section
        $searchBox = Box::newHorizontalBox();
        Box::setPadded($searchBox, true);
        
        $searchEntry = Entry::create();
        Entry::setText($searchEntry, '');
        $searchBtn = Button::create('Search');
        
        Box::append($searchBox, $searchEntry, true);
        Box::append($searchBox, $searchBtn, false);
        
        Box::append($formBox, $searchBox, false);
        Box::append($formBox, Separator::createHorizontal(), false);
        
        // Table section
        $tableBox = Box::newVerticalBox();
        Box::setPadded($tableBox, true);
        
        // Sample data for table
        $contactsOrigin = [
            ['Lisa Sky', 'lisa@sky.com', '720-523-4329', 'Denver', 'CO'],
            ['Jordan Biggins', 'jordan@biggins.', '617-528-5399', 'Boston', 'MA'],
            ['Mary Glass', 'mary@glass.con', '847-589-8788', 'Elk Grove Village', 'IL'],
            ['Darren McGrath', 'darren@mcgrat', '206-539-9283', 'Seattle', 'WA'],
            ['Melody Hanheir', 'melody@hanhei', '213-493-8274', 'Los Angeles', 'CA'],
        ];
        $contacts = $contactsOrigin;
        $filteredContacts = $contactsOrigin;
        
        // Fields for table
        $fields = ['Name', 'Email', 'Phone', 'City', 'State'];
        
        // Table model handler
        $getTableModelHandler = function() use (&$filteredContacts) {
            return Table::modelHandler(
                5,
                TableValueType::String,
                count($filteredContacts),
                function ($handler, $row, $column) use (&$filteredContacts) {
                    return Table::createValueStr($filteredContacts[$row][$column]);
                },
                function ($handler, $row, $column, $v) use (&$filteredContacts) {
                    // Can be extended for editing functionality
                }
            );
        };
        
        $tableModel = Table::createModel($getTableModelHandler());
        $table = Table::create($tableModel, -1);
        
        // Append columns to table
        Table::appendTextColumn($table, 'Name', 0, false);
        Table::appendTextColumn($table, 'Email', 1, false);
        Table::appendTextColumn($table, 'Phone', 2, false);
        Table::appendTextColumn($table, 'City', 3, false);
        Table::appendTextColumn($table, 'State', 4, false);
        
        Box::append($tableBox, $table, true);
        
        // Add form and table to main box
        Box::append($configMainBox, $formBox, false);
        Box::append($configMainBox, $tableBox, true);
        
        // Set window content
        Window::setChild($configWindow, $configMainBox);
        
        // Show window
        Control::show($configWindow);
        
        // Make sure the manage window is displayed on top of the main window
        Window::setParent($configWindow, $window);
        
        // Save contact button click handler
        Button::onClicked($saveBtn, function () use (&$contacts, &$filteredContacts, $nameEntry, $emailEntry, $phoneEntry, $cityEntry, $stateEntry, $configWindow, $table, $getTableModelHandler, $tableModel) {
            $row = [];
            $allFilled = true;
            foreach (['Name', 'Email', 'Phone', 'City', 'State'] as $field) {
                $val = Entry::text(${$field . 'Entry'});
                if (trim($val) === '') {
                    $allFilled = false;
                }
                $row[] = $val;
            }
            if ($allFilled) {
                foreach (['Name', 'Email', 'Phone', 'City', 'State'] as $field) {
                    Entry::setText(${$field . 'Entry'}, '');
                }
                $contactsOrigin[] = $row;
                $contacts = $contactsOrigin;
                $filteredContacts = $contactsOrigin;
                Table::modelRowInserted($tableModel, count($filteredContacts)-1);
            }
        });
        
        // Search button click handler
        Button::onClicked($searchBtn, function () use (&$contacts, &$filteredContacts, $searchEntry, $table, $getTableModelHandler, $tableModel) {
            global $contactsOrigin;
            $keyword = trim(Entry::text($searchEntry));
            $filteredContacts = [];
            if ($keyword === '') {
                $filteredContacts = $contactsOrigin;
            } else {
                foreach ($contactsOrigin as $row) {
                    $found = false;
                    foreach ($row as $cell) {
                        if (strpos($cell, $keyword) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if ($found) {
                        $filteredContacts[] = $row;
                    }
                }
            }
            // Clear and rebuild table
            for ($i=0; $i < count($contacts); $i++) { 
                Table::modelRowDeleted($tableModel, count($contacts) -($i+1));
            }
            $contacts = $filteredContacts;
            foreach ($filteredContacts as $i => $row) {
                Table::modelRowInserted($tableModel, $i);
            }
        });
    });

    // Add event handler for manage button
    Button::onClicked($manageButton, function() {
        // Create a new window for configuration management
        $configWindow = Window::create('Configuration Management', 600, 400, 0);
        Window::setMargined($configWindow, true);
        
        // Create main vertical box for layout
        $configMainBox = Box::newVerticalBox();
        Box::setPadded($configMainBox, true);
        
        // Form section with input fields
        $formBox = Box::newVerticalBox();
        Box::setPadded($formBox, true);
        
        // Name field
        $nameLabel = Label::create('Name:');
        Box::append($formBox, $nameLabel, false);
        $nameEntry = Entry::create();
        Box::append($formBox, $nameEntry, false);
        
        // Email field
        $emailLabel = Label::create('Email:');
        Box::append($formBox, $emailLabel, false);
        $emailEntry = Entry::create();
        Box::append($formBox, $emailEntry, false);
        
        // Phone field
        $phoneLabel = Label::create('Phone:');
        Box::append($formBox, $phoneLabel, false);
        $phoneEntry = Entry::create();
        Box::append($formBox, $phoneEntry, false);
        
        // City field
        $cityLabel = Label::create('City:');
        Box::append($formBox, $cityLabel, false);
        $cityEntry = Entry::create();
        Box::append($formBox, $cityEntry, false);
        
        // State field
        $stateLabel = Label::create('State:');
        Box::append($formBox, $stateLabel, false);
        $stateEntry = Entry::create();
        Box::append($formBox, $stateEntry, false);
        
        // Save button
        $saveButton = Button::create('Save Configuration');
        Box::append($formBox, $saveButton, false);
        
        Box::append($configMainBox, $formBox, false);
        
        // Table section
        $tableBox = Box::newVerticalBox();
        Box::setPadded($tableBox, true);
        
        // Table headers
        $headersBox = Box::newHorizontalBox();
        Box::setPadded($headersBox, true);
        
        $nameHeader = Label::create('Name');
        Box::append($headersBox, $nameHeader, true);
        
        $emailHeader = Label::create('Email');
        Box::append($headersBox, $emailHeader, true);
        
        $phoneHeader = Label::create('Phone');
        Box::append($headersBox, $phoneHeader, true);
        
        $cityHeader = Label::create('City');
        Box::append($headersBox, $cityHeader, true);
        
        $stateHeader = Label::create('State');
        Box::append($headersBox, $stateHeader, true);
        
        Box::append($tableBox, $headersBox, false);
        
        // Sample data rows
        $sampleData = [
            ['Lisa Sky', 'lisa@sky.com', '720-523-4329', 'Denver', 'CO'],
            ['Jordan Biggins', 'jordan@biggins.', '617-528-5399', 'Boston', 'MA'],
            ['Mary Glass', 'mary@glass.con', '847-589-8788', 'Elk Grove Village', 'IL'],
            ['Darren McGrath', 'darren@mcgrat', '206-539-9283', 'Seattle', 'WA'],
            ['Melody Hanheir', 'melody@hanhei', '213-493-8274', 'Los Angeles', 'CA'],
        ];
        
        foreach ($sampleData as $row) {
            $rowBox = Box::newHorizontalBox();
            Box::setPadded($rowBox, true);
            
            $nameCell = Label::create($row[0]);
            Box::append($rowBox, $nameCell, true);
            
            $emailCell = Label::create($row[1]);
            Box::append($rowBox, $emailCell, true);
            
            $phoneCell = Label::create($row[2]);
            Box::append($rowBox, $phoneCell, true);
            
            $cityCell = Label::create($row[3]);
            Box::append($rowBox, $cityCell, true);
            
            $stateCell = Label::create($row[4]);
            Box::append($rowBox, $stateCell, true);
            
            Box::append($tableBox, $rowBox, false);
        }
        
        Box::append($configMainBox, $tableBox, true);
        
        // Search section
        $searchBox = Box::newHorizontalBox();
        Box::setPadded($searchBox, true);
        
        $searchLabel = Label::create('Search:');
        Box::append($searchBox, $searchLabel, false);
        
        $searchEntry = Entry::create();
        Box::append($searchBox, $searchEntry, true);
        
        $searchButton = Button::create('Search');
        Box::append($searchBox, $searchButton, false);
        
        Box::append($configMainBox, $searchBox, false);
        
        // Set window content
        Window::setChild($configWindow, $configMainBox);
        
        // Show window
        Control::show($configWindow);
    });

    // Add event handler for manage button
    Button::onClicked($manageButton, function() {
        // Create a new window for configuration management
        $configWindow = Window::create('Configuration Management', 800, 600, 0);
        Window::setMargined($configWindow, true);
        
        // Handle window closing
        Window::onClosing($configWindow, function($window) {
            App::quit();
            return 1;
        });
        
        // Create main vertical box for layout
        $configMainBox = Box::newVerticalBox();
        Box::setPadded($configMainBox, true);
        
        // Create form for adding/editing configurations
        $formBox = Box::newVerticalBox();
        Box::setPadded($formBox, true);
        
        // Name field
        $nameLabel = Label::create('Name:');
        Box::append($formBox, $nameLabel, false);
        $nameEntry = Entry::create();
        Box::append($formBox, $nameEntry, false);
        
        // Email field
        $emailLabel = Label::create('Email:');
        Box::append($formBox, $emailLabel, false);
        $emailEntry = Entry::create();
        Box::append($formBox, $emailEntry, false);
        
        // Phone field
        $phoneLabel = Label::create('Phone:');
        Box::append($formBox, $phoneLabel, false);
        $phoneEntry = Entry::create();
        Box::append($formBox, $phoneEntry, false);
        
        // City field
        $cityLabel = Label::create('City:');
        Box::append($formBox, $cityLabel, false);
        $cityEntry = Entry::create();
        Box::append($formBox, $cityEntry, false);
        
        // State field
        $stateLabel = Label::create('State:');
        Box::append($formBox, $stateLabel, false);
        $stateEntry = Entry::create();
        Box::append($formBox, $stateEntry, false);
        
        // Save button
        $saveBtn = Button::create('Save Contact');
        Box::append($formBox, $saveBtn, false);
        
        // Separator
        Box::append($formBox, Separator::createHorizontal(), false);
        
        // Search box
        $searchEntry = Entry::create();
        Entry::setText($searchEntry, '');
        $searchBtn = Button::create('Search');
        $searchBox = Box::newHorizontalBox();
        Box::setPadded($searchBox, true);
        Box::append($searchBox, $searchEntry, true);
        Box::append($searchBox, $searchBtn, false);
        Box::append($formBox, $searchBox, false);
        
        // Separator
        Box::append($formBox, Separator::createHorizontal(), false);
        
        // Table for displaying contacts
        $contactsOrigin = [
            ['Lisa Sky', 'lisa@sky.com', '720-523-4329', 'Denver', 'CO'],
            ['Jordan Biggins', 'jordan@biggins.', '617-528-5399', 'Boston', 'MA'],
            ['Mary Glass', 'mary@glass.con', '847-589-8788', 'Elk Grove Village', 'IL'],
            ['Darren McGrath', 'darren@mcgrat', '206-539-9283', 'Seattle', 'WA'],
            ['Melody Hanheir', 'melody@hanhei', '213-493-8274', 'Los Angeles', 'CA'],
        ];
        $contacts = $contactsOrigin;
        $filteredContacts = $contactsOrigin;
        
        $fields = ['Name', 'Email', 'Phone', 'City', 'State'];
        
        // Table model handler
        $getTableModelHandler = function() use (&$filteredContacts) {
            return Table::modelHandler(
                5,
                TableValueType::String,
                count($filteredContacts),
                function ($handler, $row, $column) use (&$filteredContacts) {
                    return Table::createValueStr($filteredContacts[$row][$column]);
                },
                function ($handler, $row, $column, $v) use (&$filteredContacts) {
                    // Can be extended for editing functionality
                }
            );
        };
        
        $tableModel = Table::createModel($getTableModelHandler());
        $table = Table::create($tableModel, -1);
        Table::appendTextColumn($table, 'Name', 0, false);
        Table::appendTextColumn($table, 'Email', 1, false);
        Table::appendTextColumn($table, 'Phone', 2, false);
        Table::appendTextColumn($table, 'City', 3, false);
        Table::appendTextColumn($table, 'State', 4, false);
        Box::append($formBox, $table, true);
        
        // Save contact event
        Button::onClicked($saveBtn, function () use (&$contacts, &$filteredContacts, $nameEntry, $emailEntry, $phoneEntry, $cityEntry, $stateEntry, $configWindow, $table, $getTableModelHandler, $tableModel) {
            $row = [];
            $allFilled = true;
            foreach (['Name', 'Email', 'Phone', 'City', 'State'] as $field) {
                $val = Entry::text(${$field . 'Entry'});
                if (trim($val) === '') {
                    $allFilled = false;
                }
                $row[] = $val;
            }
            if ($allFilled) {
                foreach ([$nameEntry, $emailEntry, $phoneEntry, $cityEntry, $stateEntry] as $entry) {
                    Entry::setText($entry, '');
                }
                $contactsOrigin[] = $row;
                $contacts = $contactsOrigin;
                $filteredContacts = $contactsOrigin;
                Table::modelRowInserted($tableModel, count($filteredContacts)-1);
            }
        });
        
        // Search filter event
        Button::onClicked($searchBtn, function () use (&$contacts, &$filteredContacts, $searchEntry, $table, $getTableModelHandler, $tableModel) {
            global $contactsOrigin;
            $keyword = trim(Entry::text($searchEntry));
            $filteredContacts = [];
            if ($keyword === '') {
                $filteredContacts = $contactsOrigin;
            } else {
                foreach ($contactsOrigin as $row) {
                    $found = false;
                    foreach ($row as $cell) {
                        if (strpos($cell, $keyword) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if ($found) {
                        $filteredContacts[] = $row;
                    }
                }
            }
            // Clear and rebuild table content
            for ($i=0; $i < count($contacts); $i++) { 
                Table::modelRowDeleted($tableModel, count($contacts) -($i+1));
            }
            $contacts = $filteredContacts;
            foreach ($filteredContacts as $i => $row) {
                Table::modelRowInserted($tableModel, $i);
            }
        });
        
        // Set window content
        Window::setChild($configWindow, $formBox);
        
        // Show window
        Control::show($configWindow);
        
        // Run the main event loop
        App::main();
    });

    // Add event handler for manage button
    Button::onClicked($manageButton, function() use ($window) {
        // Create a new window for configuration management
        $configWindow = Window::create('Configuration Management', 600, 400, 0);
        Window::setMargined($configWindow, true);
        
        // Handle window closing
        Window::onClosing($configWindow, function($configWindow) {
            Window::destroy($configWindow);
            return 1;
        });
        
        // Create form for configuration management
        $formBox = Box::newVerticalBox();
        Box::setPadded($formBox, true);
        
        // Add configuration form fields
        $nameLabel = Label::create('Name:');
        Box::append($formBox, $nameLabel, false);
        
        $nameEntry = Entry::create();
        Box::append($formBox, $nameEntry, false);
        
        $urlLabel = Label::create('URL:');
        Box::append($formBox, $urlLabel, false);
        
        $urlEntry = Entry::create();
        Box::append($formBox, $urlEntry, false);
        
        $concurrentLabel = Label::create('Concurrent Connections:');
        Box::append($formBox, $concurrentLabel, false);
        
        $concurrentEntry = Entry::create();
        Box::append($formBox, $concurrentEntry, false);
        
        $durationLabel = Label::create('Duration (seconds):');
        Box::append($formBox, $durationLabel, false);
        
        $durationEntry = Entry::create();
        Box::append($formBox, $durationEntry, false);
        
        // Save button
        $saveButton = Button::create('Save Configuration');
        Box::append($formBox, $saveButton, false);
        
        // Add separator
        Box::append($formBox, Separator::createHorizontal(), false);
        
        // Search field
        $searchLabel = Label::create('Search:');
        Box::append($formBox, $searchLabel, false);
        
        $searchEntry = Entry::create();
        Box::append($formBox, $searchEntry, false);
        
        // Search button
        $searchButton = Button::create('Search');
        Box::append($formBox, $searchButton, false);
        
        // Add separator
        Box::append($formBox, Separator::createHorizontal(), false);
        
        // Table for displaying configurations
        $tableModel = Table::createModel(function() {
            // This is a placeholder - in a real implementation, you would load configurations from storage
            return Table::modelHandler(
                5,
                TableValueType::String,
                0,
                function ($handler, $row, $column) {
                    // Return empty string for now
                    return Table::createValueStr('');
                },
                function ($handler, $row, $column, $v) {
                    // No editing functionality for now
                }
            );
        });
        
        $table = Table::create($tableModel, -1);
        Table::appendTextColumn($table, 'Name', 0, false);
        Table::appendTextColumn($table, 'URL', 1, false);
        Table::appendTextColumn($table, 'Concurrent', 2, false);
        Table::appendTextColumn($table, 'Duration', 3, false);
        Table::appendTextColumn($table, 'Actions', 4, false);
        
        Box::append($formBox, $table, true);
        
        // Set content of configuration window
        Window::setChild($configWindow, $formBox);
        
        // Show the configuration window
        Control::show($configWindow);
    });

    // Add event handler for manage button
    Button::onClicked($manageButton, function() {
        // Create a new window for configuration management
        $configWindow = Window::create('Configuration Management', 800, 600, 0);
        Window::setMargined($configWindow, true);
        
        // Handle window closing
        Window::onClosing($configWindow, function($window) {
            App::quit();
            return 1;
        });
        
        // Create main vertical box for layout
        $configMainBox = Box::newVerticalBox();
        Box::setPadded($configMainBox, true);
        
        // Create form for adding/editing configurations
        $formBox = Box::newVerticalBox();
        Box::setPadded($formBox, true);
        
        // Name field
        $nameLabel = Label::create('Name:');
        Box::append($formBox, $nameLabel, false);
        $nameEntry = Entry::create();
        Box::append($formBox, $nameEntry, false);
        
        // Email field
        $emailLabel = Label::create('Email:');
        Box::append($formBox, $emailLabel, false);
        $emailEntry = Entry::create();
        Box::append($formBox, $emailEntry, false);
        
        // Phone field
        $phoneLabel = Label::create('Phone:');
        Box::append($formBox, $phoneLabel, false);
        $phoneEntry = Entry::create();
        Box::append($formBox, $phoneEntry, false);
        
        // City field
        $cityLabel = Label::create('City:');
        Box::append($formBox, $cityLabel, false);
        $cityEntry = Entry::create();
        Box::append($formBox, $cityEntry, false);
        
        // State field
        $stateLabel = Label::create('State:');
        Box::append($formBox, $stateLabel, false);
        $stateEntry = Entry::create();
        Box::append($formBox, $stateEntry, false);
        
        // Save button
        $saveBtn = Button::create('Save Contact');
        Box::append($formBox, $saveBtn, false);
        Box::append($formBox, Separator::createHorizontal(), false);
        
        // Search section
        $searchEntry = Entry::create();
        Entry::setText($searchEntry, '');
        $searchBtn = Button::create('Search');
        $searchBox = Box::newHorizontalBox();
        Box::setPadded($searchBox, true);
        Box::append($searchBox, $searchEntry, true);
        Box::append($searchBox, $searchBtn, false);
        Box::append($formBox, $searchBox, false);
        Box::append($formBox, Separator::createHorizontal(), false);
        
        // Table for displaying configurations
        $contactsOrigin = [
            ['Lisa Sky', 'lisa@sky.com', '720-523-4329', 'Denver', 'CO'],
            ['Jordan Biggins', 'jordan@biggins.', '617-528-5399', 'Boston', 'MA'],
            ['Mary Glass', 'mary@glass.con', '847-589-8788', 'Elk Grove Village', 'IL'],
            ['Darren McGrath', 'darren@mcgrat', '206-539-9283', 'Seattle', 'WA'],
            ['Melody Hanheir', 'melody@hanhei', '213-493-8274', 'Los Angeles', 'CA'],
        ];
        $contacts = $contactsOrigin;
        $filteredContacts = $contactsOrigin;
        
        $fields = ['Name', 'Email', 'Phone', 'City', 'State'];
        
        // Table model handler
        $getTableModelHandler = function() use (&$filteredContacts) {
            return Table::modelHandler(
                5,
                TableValueType::String,
                count($filteredContacts),
                function ($handler, $row, $column) use (&$filteredContacts) {
                    return Table::createValueStr($filteredContacts[$row][$column]);
                },
                function ($handler, $row, $column, $v) use (&$filteredContacts) {
                    // Can extend edit functionality here
                }
            );
        };
        
        $tableModel = Table::createModel($getTableModelHandler());
        $table = Table::create($tableModel, -1);
        Table::appendTextColumn($table, 'Name', 0, false);
        Table::appendTextColumn($table, 'Email', 1, false);
        Table::appendTextColumn($table, 'Phone', 2, false);
        Table::appendTextColumn($table, 'City', 3, false);
        Table::appendTextColumn($table, 'State', 4, false);
        Box::append($formBox, $table, true);
        
        // Save contact event
        Button::onClicked($saveBtn, function () use (&$contacts, &$filteredContacts, $nameEntry, $emailEntry, $phoneEntry, $cityEntry, $stateEntry, $configWindow, $table, $getTableModelHandler, $tableModel) {
            $row = [];
            $allFilled = true;
            foreach (['Name', 'Email', 'Phone', 'City', 'State'] as $field) {
                $val = Entry::text(${$field.'Entry'});
                if (trim($val) === '') {
                    $allFilled = false;
                }
                $row[] = $val;
            }
            if ($allFilled) {
                foreach (['Name', 'Email', 'Phone', 'City', 'State'] as $field) {
                    Entry::setText(${$field.'Entry'}, '');
                }
                $contactsOrigin[] = $row;
                $contacts = $contactsOrigin;
                $filteredContacts = $contactsOrigin;
                Table::modelRowInserted($tableModel, count($filteredContacts)-1);
            }
        });
        
        // Search filter event
        Button::onClicked($searchBtn, function () use (&$contacts, &$filteredContacts, $searchEntry, $table, $getTableModelHandler, $tableModel) {
            global $contactsOrigin;
            $keyword = trim(Entry::text($searchEntry));
            $filteredContacts = [];
            if ($keyword === '') {
                $filteredContacts = $contactsOrigin;
            } else {
                foreach ($contactsOrigin as $row) {
                    $found = false;
                    foreach ($row as $cell) {
                        if (strpos($cell, $keyword) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if ($found) {
                        $filteredContacts[] = $row;
                    }
                }
            }
            // Clear and rebuild table content
            for ($i=0; $i < count($contacts); $i++) { 
                Table::modelRowDeleted($tableModel, count($contacts) -($i+1));
            }
            $contacts = $filteredContacts;
            foreach ($filteredContacts as $i => $row) {
                Table::modelRowInserted($tableModel, $i);
            }
        });
        
        // Set window content
        Window::setChild($configWindow, $formBox);
        Control::show($configWindow);
    });

    // Add event handler for Manage button
    Button::onClicked($manageButton, function() use ($window) {
        // Create a new window for configuration management
        $configWindow = Window::create('Configuration Management', 800, 600, 0);
        Window::setMargined($configWindow, true);
        
        // Handle window closing
        Window::onClosing($configWindow, function($configWindow) {
            App::quit();
            return 1;
        });
        
        // Create main vertical box for layout
        $configMainBox = Box::newVerticalBox();
        Box::setPadded($configMainBox, true);
        
        // Form section
        $formBox = Box::newVerticalBox();
        Box::setPadded($formBox, true);
        
        // Fields for configuration
        $fields = ['Name', 'URL', 'Concurrent Connections', 'Duration', 'Timeout'];
        $entries = [];
        foreach ($fields as $field) {
            $label = Label::create($field);
            $entry = Entry::create();
            Box::append($formBox, $label, false);
            Box::append($formBox, $entry, false);
            $entries[$field] = $entry;
        }
        
        // Save button
        $saveBtn = Button::create('Save Configuration');
        Box::append($formBox, $saveBtn, false);
        Box::append($formBox, Separator::createHorizontal(), false);
        
        // Search section
        $searchEntry = Entry::create();
        Entry::setText($searchEntry, '');
        $searchBtn = Button::create('Search');
        $searchBox = Box::newHorizontalBox();
        Box::setPadded($searchBox, true);
        Box::append($searchBox, $searchEntry, true);
        Box::append($searchBox, $searchBtn, false);
        Box::append($formBox, $searchBox, false);
        Box::append($formBox, Separator::createHorizontal(), false);
        
        // Table model handler
        $getTableModelHandler = function() use (&$filteredConfigurations) {
            return Table::modelHandler(
                5,
                TableValueType::String,
                count($filteredConfigurations),
                function ($handler, $row, $column) use (&$filteredConfigurations) {
                    return Table::createValueStr($filteredConfigurations[$row][$column]);
                },
                function ($handler, $row, $column, $v) use (&$filteredConfigurations) {
                    // Can be extended for editing functionality
                }
            );
        };
        
        // Initialize configurations
        $configurations = [
            ['Default Config', 'https://example.com', '10', '30s', '5s'],
            ['High Load Test', 'https://api.example.com', '100', '60s', '10s'],
            ['Stress Test', 'https://stress.example.com', '500', '120s', '15s']
        ];
        $filteredConfigurations = $configurations;
        
        // Create table model and table
        $tableModel = Table::createModel($getTableModelHandler());
        $table = Table::create($tableModel, -1);
        Table::appendTextColumn($table, 'Name', 0, false);
        Table::appendTextColumn($table, 'URL', 1, false);
        Table::appendTextColumn($table, 'Concurrent Connections', 2, false);
        Table::appendTextColumn($table, 'Duration', 3, false);
        Table::appendTextColumn($table, 'Timeout', 4, false);
        Box::append($formBox, $table, true);
        
        // Save button event handler
        Button::onClicked($saveBtn, function () use (&$configurations, &$filteredConfigurations, $entries, $configWindow, $table, $getTableModelHandler, $tableModel) {
            $row = [];
            $allFilled = true;
            foreach (['Name', 'URL', 'Concurrent Connections', 'Duration', 'Timeout'] as $field) {
                $val = Entry::text($entries[$field]);
                if (trim($val) === '') {
                    $allFilled = false;
                }
                $row[] = $val;
            }
            if ($allFilled) {
                foreach ($entries as $entry) {
                    Entry::setText($entry, '');
                }
                $configurations[] = $row;
                $filteredConfigurations = $configurations;
                Table::modelRowInserted($tableModel, count($filteredConfigurations)-1);
            }
        });
        
        // Search button event handler
        Button::onClicked($searchBtn, function () use (&$configurations, &$filteredConfigurations, $searchEntry, $table, $getTableModelHandler, $tableModel) {
            global $configurations;
            $keyword = trim(Entry::text($searchEntry));
            $filteredConfigurations = [];
            if ($keyword === '') {
                $filteredConfigurations = $configurations;
            } else {
                foreach ($configurations as $row) {
                    $found = false;
                    foreach ($row as $cell) {
                        if (strpos($cell, $keyword) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if ($found) {
                        $filteredConfigurations[] = $row;
                    }
                }
            }
            // Clear and rebuild table content
            for ($i=0; $i < count($configurations); $i++) { 
                Table::modelRowDeleted($tableModel, count($configurations) -($i+1));
            }
            $configurations = $filteredConfigurations;
            foreach ($filteredConfigurations as $i => $row) {
                Table::modelRowInserted($tableModel, $i);
            }
        });
        
        Window::setChild($configWindow, $formBox);
        Control::show($configWindow);
    });

    Group::setChild($inputGroup, $inputBox);
    Box::append($topHorizontalBox, $inputGroup, true);

    // Results group (right side)
    $resultsGroup = Group::create('结果');
    $resultsBox = Box::newVerticalBox();
    Box::setPadded($resultsBox, true);

    // Create horizontal result groups
    $resultGroupsBox = Box::newHorizontalBox();
    Box::setPadded($resultGroupsBox, true);

    // Results text
    $resultsText = Label::create("Ready to run test\nRequests/sec: --\nTotal requests: --\nSuccess rate: --\nPerformance: --");
    Box::append($resultsBox, $resultsText, false);

    Box::append($resultsBox, $resultGroupsBox, false);

    Group::setChild($resultsGroup, $resultsBox);
    Box::append($topHorizontalBox, $resultsGroup, true);

    Box::append($mainBox, $topHorizontalBox, false);

    // Second horizontal box - contains title and multiline text box
    $bottomHorizontalBox = Box::newHorizontalBox();
    Box::setPadded($bottomHorizontalBox, true);

    // Output group
    $outputGroup = Group::create('测试输出');
    $outputBox = Box::newVerticalBox();
    Box::setPadded($outputBox, true);
    
    // Add output label with placeholder text
    $outputLabel = Label::create('Test output will appear here...');
    Box::append($outputBox, $outputLabel, false);
    
    Group::setChild($outputGroup, $outputBox);
    Box::append($bottomHorizontalBox, $outputGroup, true);

    Box::append($mainBox, $bottomHorizontalBox, true);

    // Set window content
    Window::setChild($window, $mainBox);

    // Set window content
    Window::setChild($window, $mainBox);

    // Show window
    Control::show($window);

    // Run the main event loop
    App::main();

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    exit(1);
}