<?php

require_once __DIR__ . '/../src/Build/FileScanner.php';

use TypePhp\Build\FileScanner;

// 创建文件扫描器实例，用于扫描 PHP 和 C++ 文件
$scanner = new FileScanner(['.php', '.cc', '.cpp', '.cxx', '.c', '.h', '.hpp']);

// 添加排除模式（可选）
$scanner->addExcludePattern('*/vendor/*');
$scanner->addExcludePattern('*/node_modules/*');
$scanner->addExcludePattern('*.log');
$scanner->addExcludePattern('*/tests/aot/*');

// 扫描当前项目目录
echo "扫描项目中的 PHP 和 C++ 文件...\n";

try {
    $files = $scanner->scan(__DIR__ . '/..'); // 扫描项目根目录
    
    echo "找到 " . count($files) . " 个文件:\n";
    
    foreach ($files as $index => $file) {
        echo ($index + 1) . ". " . $file . "\n";
    }
    
    echo "\n带统计信息的扫描:\n";
    $result = $scanner->scanWithStats(__DIR__ . '/..');
    
    echo "总共找到 {$result['count']} 个文件\n";
    echo "按扩展名统计:\n";
    foreach ($result['stats'] as $ext => $count) {
        echo "  $ext: $count 个文件\n";
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
