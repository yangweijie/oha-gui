#!/usr/bin/env php
<?php

// 测试 PHAR 包中的 Base.php 文件
echo "Testing Base.php in PHAR environment...\n";

// 检查是否在 PHAR 环境中运行
echo "PHP_OS_FAMILY: " . PHP_OS_FAMILY . "\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "__FILE__: " . __FILE__ . "\n";
echo "strpos(__DIR__, 'phar://'): " . (strpos(__DIR__, 'phar://') === 0 ? "true" : "false") . "\n";
echo "strpos(__FILE__, 'phar://'): " . (strpos(__FILE__, 'phar://') === 0 ? "true" : "false") . "\n";

// 检查是否是通过 PHAR 文件运行的
$isPharExecution = isset($_SERVER['argv'][0]) && 
                   is_string($_SERVER['argv'][0]) && 
                   strpos($_SERVER['argv'][0], '.phar') !== false;

echo "isPharExecution: " . ($isPharExecution ? "true" : "false") . "\n";
echo "argv[0]: " . ($_SERVER['argv'][0] ?? 'undefined') . "\n";

// 尝试加载 Kingbes\\Libui\\Base 类
echo "\nAttempting to load Kingbes\\Libui\\Base class...\n";
if (class_exists('Kingbes\\Libui\\Base')) {
    echo "Kingbes\\Libui\\Base class loaded successfully!\n";
    
    // 尝试调用 getLibFilePath 方法
    try {
        echo "\nTesting getLibFilePath() method...\n";
        // 使用反射来调用受保护的方法
        $class = new ReflectionClass('Kingbes\\Libui\\Base');
        $method = $class->getMethod('getLibFilePath');
        $method->setAccessible(true);
        
        $result = $method->invoke(null);
        echo "getLibFilePath() returned: " . $result . "\n";
        
        // 检查文件是否存在
        if (file_exists($result)) {
            echo "Library file exists: " . $result . "\n";
            echo "File size: " . filesize($result) . " bytes\n";
        } else {
            echo "Library file does not exist: " . $result . "\n";
        }
    } catch (Exception $e) {
        echo "Error calling getLibFilePath(): " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    } catch (Throwable $e) {
        echo "Error calling getLibFilePath(): " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
    
    // 尝试调用 ffi 方法
    try {
        echo "\nTesting ffi() method...\n";
        $ffi = \Kingbes\Libui\Base::ffi();
        echo "Successfully loaded libui library via FFI!\n";
    } catch (Exception $e) {
        echo "Error loading libui library via FFI: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    } catch (Throwable $e) {
        echo "Error loading libui library via FFI: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
} else {
    echo "Kingbes\\Libui\\Base class not found!\n";
}

echo "\nTest completed.\n";