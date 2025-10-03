<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

// 测试 Base.php 中的 getLibFilePath 方法
try {
    echo "Testing getLibFilePath() method...\n";
    
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
    
    echo "Test completed.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}