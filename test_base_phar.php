#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Kingbes\Libui\Base;

echo "Testing Base.php methods in PHAR-like environment...\n";

// 模拟 PHAR 环境
$_SERVER['argv'][0] = './oha-gui.phar';

echo "Simulating PHAR environment...\n";
echo "PHP_OS_FAMILY: " . PHP_OS_FAMILY . "\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "argv[0]: " . ($_SERVER['argv'][0] ?? 'undefined') . "\n";

// 检查类是否存在
if (class_exists('Kingbes\\Libui\\Base')) {
    echo "Kingbes\\Libui\\Base class exists\n";
    
    // 使用反射检查方法是否存在
    $class = new ReflectionClass('Kingbes\\Libui\\Base');
    
    if ($class->hasMethod('getLibFilePath')) {
        echo "getLibFilePath method exists\n";
    } else {
        echo "getLibFilePath method does not exist\n";
    }
    
    if ($class->hasMethod('extractLibFile')) {
        echo "extractLibFile method exists\n";
    } else {
        echo "extractLibFile method does not exist\n";
    }
    
    // 检查 PHAR 环境检测
    $inPhar = defined('PATH_SEPARATOR') && 
              (strpos(__DIR__, 'phar://') === 0 || 
               strpos(__FILE__, 'phar://') === 0);
    
    $isPharExecution = isset($_SERVER['argv'][0]) && 
                       is_string($_SERVER['argv'][0]) && 
                       strpos($_SERVER['argv'][0], '.phar') !== false;
    
    echo "inPhar: " . ($inPhar ? "true" : "false") . "\n";
    echo "isPharExecution: " . ($isPharExecution ? "true" : "false") . "\n";
    
    // 尝试调用方法
    try {
        // 使用反射调用受保护的方法
        $method = $class->getMethod('getLibFilePath');
        $method->setAccessible(true);
        $result = $method->invoke(null);
        echo "getLibFilePath returned: " . $result . "\n";
        
        // 检查文件是否存在
        if (file_exists($result)) {
            echo "File exists: " . $result . "\n";
            echo "File size: " . filesize($result) . " bytes\n";
        } else {
            echo "File does not exist: " . $result . "\n";
        }
        
        // 检查是否是临时目录中的文件
        $tempDir = sys_get_temp_dir();
        if (strpos($result, $tempDir) === 0) {
            echo "File is in temporary directory\n";
        } else {
            echo "File is NOT in temporary directory\n";
        }
    } catch (Exception $e) {
        echo "Error calling getLibFilePath: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
} else {
    echo "Kingbes\\Libui\\Base class does not exist\n";
}