<?php

/**
 * Simple test runner for OHA GUI Tool configuration management tests
 * This script runs the unit tests for ConfigurationManager and ConfigurationValidator
 */

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

// Simple assertion functions
function assertTrue($condition, $message = 'Assertion failed') {
    if (!$condition) {
        throw new Exception($message);
    }
}

function assertFalse($condition, $message = 'Assertion failed') {
    if ($condition) {
        throw new Exception($message);
    }
}

function assertEquals($expected, $actual, $message = 'Values are not equal') {
    if ($expected !== $actual) {
        throw new Exception($message . " Expected: " . var_export($expected, true) . ", Actual: " . var_export($actual, true));
    }
}

function assertNotEquals($expected, $actual, $message = 'Values should not be equal') {
    if ($expected === $actual) {
        throw new Exception($message);
    }
}

function assertNull($value, $message = 'Value is not null') {
    if ($value !== null) {
        throw new Exception($message);
    }
}

function assertNotNull($value, $message = 'Value is not null') {
    if ($value === null) {
        throw new Exception($message);
    }
}

function assertEmpty($value, $message = 'Value is not empty') {
    if (!empty($value)) {
        throw new Exception($message);
    }
}

function assertNotEmpty($value, $message = 'Value is empty') {
    if (empty($value)) {
        throw new Exception($message);
    }
}

function assertContains($needle, $haystack, $message = 'Value not found in array') {
    if (!in_array($needle, $haystack)) {
        throw new Exception($message);
    }
}

function assertArrayHasKey($key, $array, $message = 'Key not found in array') {
    if (!array_key_exists($key, $array)) {
        throw new Exception($message);
    }
}

function assertIsArray($value, $message = 'Value is not an array') {
    if (!is_array($value)) {
        throw new Exception($message);
    }
}

function assertCount($expectedCount, $array, $message = 'Array count mismatch') {
    if (count($array) !== $expectedCount) {
        throw new Exception($message . " Expected: {$expectedCount}, Actual: " . count($array));
    }
}

function assertGreaterThan($expected, $actual, $message = 'Value is not greater than expected') {
    if ($actual <= $expected) {
        throw new Exception($message);
    }
}

// Simple test runner function
function runTests($testClass) {
    echo "Running tests for {$testClass}...\n";
    
    $reflection = new ReflectionClass($testClass);
    $instance = $reflection->newInstance();
    
    // Run setUp if it exists
    if ($reflection->hasMethod('setUp')) {
        $instance->setUp();
    }
    
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    $testMethods = array_filter($methods, function($method) {
        return strpos($method->getName(), 'test') === 0;
    });
    
    $passed = 0;
    $failed = 0;
    
    foreach ($testMethods as $method) {
        try {
            echo "  - {$method->getName()}... ";
            $method->invoke($instance);
            echo "PASSED\n";
            $passed++;
        } catch (Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
            $failed++;
        }
        
        // Run tearDown if it exists
        if ($reflection->hasMethod('tearDown')) {
            $instance->tearDown();
        }
        
        // Run setUp again for next test
        if ($reflection->hasMethod('setUp')) {
            $instance->setUp();
        }
    }
    
    echo "Results: {$passed} passed, {$failed} failed\n\n";
    return $failed === 0;
}

// Load test classes
require_once 'tests/Core/ConfigurationValidatorTest.php';
require_once 'tests/Core/ConfigurationManagerTest.php';
require_once 'tests/Core/OhaCommandBuilderTest.php';

echo "OHA GUI Tool - Configuration Management Tests\n";
echo "=============================================\n\n";

$allPassed = true;

// Run ConfigurationValidator tests
$allPassed &= runTests('ConfigurationValidatorTest');

// Run ConfigurationManager tests  
$allPassed &= runTests('ConfigurationManagerTest');

// Run OhaCommandBuilder tests
$allPassed &= runTests('OhaCommandBuilderTest');

if ($allPassed) {
    echo "All tests passed! ✅\n";
    exit(0);
} else {
    echo "Some tests failed! ❌\n";
    exit(1);
}