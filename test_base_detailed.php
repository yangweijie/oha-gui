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
    
    // 使用反射检查所有方法
    $class = new ReflectionClass('Kingbes\\Libui\\Base');
    $methods = $class->getMethods();
    
    echo "All methods in Kingbes\\Libui\\Base:\n";
    foreach ($methods as $method) {
        echo "- " . $method->getName() . " (" . ($method->isPublic() ? "public" : ($method->isProtected() ? "protected" : "private")) . ")\n";
    }
    
    // 检查 PHAR 环境检测
    $inPhar = defined('PATH_SEPARATOR') && 
              (strpos(__DIR__, 'phar://') === 0 || 
               strpos(__FILE__, 'phar://') === 0);
    
    $isPharExecution = isset($_SERVER['argv'][0]) && 
                       is_string($_SERVER['argv'][0]) && 
                       strpos($_SERVER['argv'][0], '.phar') !== false;
    
    echo "\ninPhar: " . ($inPhar ? "true" : "false") . "\n";
    echo "isPharExecution: " . ($isPharExecution ? "true" : "false") . "\n";
    
    // 直接测试 extractLibFile 方法
    try {
        $method = $class->getMethod('extractLibFile');
        echo "extractLibFile method found\n";
        $method->setAccessible(true);
        echo "extractLibFile method is accessible\n";
    } catch (Exception $e) {
        echo "Error finding extractLibFile method: " . $e->getMessage() . "\n";
    }
    
    // 尝试调用 getLibFilePath 方法
    try {
        $method = $class->getMethod('getLibFilePath');
        $method->setAccessible(true);
        $result = $method->invoke(null);
        echo "getLibFilePath returned: " . $result . "\n";
    } catch (Exception $e) {
        echo "Error calling getLibFilePath: " . $e->getMessage() . "\n";
    }
} else {
    echo "Kingbes\\Libui\\Base class does not exist\n";
}