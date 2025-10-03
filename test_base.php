#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Kingbes\Libui\Base;

echo "Testing Base.php methods...\n";

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
    } catch (Exception $e) {
        echo "Error calling getLibFilePath: " . $e->getMessage() . "\n";
    }
} else {
    echo "Kingbes\\Libui\\Base class does not exist\n";
}