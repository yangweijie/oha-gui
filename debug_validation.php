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

use OhaGui\Core\ResultParser;

$parser = new ResultParser();

$testOutputs = [
    'oha v0.5.4' => true,
    'Requests/sec: 123.45' => true,
    'Success rate: 95.5%' => true,
    'Total: 1000 requests' => true,
    'Random text without oha indicators' => false
];

foreach ($testOutputs as $output => $expected) {
    $result = $parser->isValidOhaOutput($output);
    echo "Output: '$output'\n";
    echo "Expected: " . ($expected ? 'true' : 'false') . "\n";
    echo "Actual: " . ($result ? 'true' : 'false') . "\n";
    echo "Match: " . ($result === $expected ? 'YES' : 'NO') . "\n\n";
}

// Test error parsing
$errorOutput = "
Summary:
  Success rate: 90.00%
  Total: 1000 requests
  Connection errors: 25
  Timeout errors: 15
  Read errors: 10
  Write errors: 0
";

$errors = $parser->parseErrors($errorOutput);
echo "Error parsing test:\n";
print_r($errors);