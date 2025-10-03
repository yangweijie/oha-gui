#!/usr/bin/env php
<?php

// 运行 PHAR 包中的测试脚本
echo "Running test script from PHAR package...\n";

// 执行 PHAR 包中的测试脚本
$result = include 'phar://./oha-gui.phar/test_phar_base.php';

if ($result === false) {
    echo "Failed to execute test script from PHAR package.\n";
} else {
    echo "Test script executed successfully.\n";
}