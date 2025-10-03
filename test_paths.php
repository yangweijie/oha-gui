<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

// 测试在 PHAR 环境中的路径解析
echo "Testing paths in normal environment...\n";
echo "PHP_OS_FAMILY: " . PHP_OS_FAMILY . "\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "__FILE__: " . __FILE__ . "\n";
echo "strpos(__DIR__, 'phar://'): " . (strpos(__DIR__, 'phar://') === 0 ? "true" : "false") . "\n";
echo "strpos(__FILE__, 'phar://'): " . (strpos(__FILE__, 'phar://') === 0 ? "true" : "false") . "\n";

// 检查是否在 PHAR 环境中运行
$inPhar = defined('PATH_SEPARATOR') && 
          (strpos(__DIR__, 'phar://') === 0 || 
           strpos(__FILE__, 'phar://') === 0);

echo "inPhar: " . ($inPhar ? "true" : "false") . "\n";

// 检查是否是通过 PHAR 文件运行的
$isPharExecution = isset($_SERVER['argv'][0]) && 
                   is_string($_SERVER['argv'][0]) && 
                   strpos($_SERVER['argv'][0], '.phar') !== false;

echo "isPharExecution: " . ($isPharExecution ? "true" : "false") . "\n";
echo "argv[0]: " . ($_SERVER['argv'][0] ?? 'undefined') . "\n";

echo "\nTesting libui library loading...\n";

try {
    // 尝试直接调用 ffi() 方法
    $ffi = \Kingbes\Libui\Base::ffi();
    echo "Successfully loaded libui library!\n";
} catch (Exception $e) {
    echo "Error loading libui library: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Throwable $e) {
    echo "Error loading libui library: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}