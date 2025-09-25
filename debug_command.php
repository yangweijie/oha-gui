<?php

require_once 'vendor/autoload.php';

// Set up autoloader for our classes
spl_autoload_register(function ($class) {
    $prefix = 'OhaGui\\';
    $base_dir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use OhaGui\Core\OhaCommandBuilder;
use OhaGui\Models\TestConfiguration;

$builder = new OhaCommandBuilder();
$builder->enableTestMode();

$config = new TestConfiguration(
    'test-config',
    'https://example.com',
    'GET',
    10,
    30,
    15
);

$command = $builder->buildCommand($config);
echo "Generated command:\n";
echo $command . "\n\n";

// Test with headers
$config2 = new TestConfiguration(
    'test-config',
    'https://api.example.com',
    'GET',
    5,
    10,
    30,
    [
        'Authorization' => 'Bearer token123',
        'Content-Type' => 'application/json'
    ]
);

$command2 = $builder->buildCommand($config2);
echo "Command with headers:\n";
echo $command2 . "\n\n";

// Test with body
$config3 = new TestConfiguration(
    'test-config',
    'https://api.example.com/users',
    'POST',
    5,
    10,
    30,
    [],
    '{"name": "John Doe"}'
);

$command3 = $builder->buildCommand($config3);
echo "Command with body:\n";
echo $command3 . "\n";