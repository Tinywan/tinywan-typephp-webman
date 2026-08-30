<?php

require_once __DIR__ . '/../src/Build/FileScanner.php';

use TypePhp\Build\FileScanner;

// 创建文件扫描器实例，用于扫描 PHP 和 C++ 文件
$scanner = new FileScanner(['.php', '.cc', '.cpp', '.cxx', '.c', '.h', '.hpp']);

// 添加排除模式
$scanner->addExcludePattern('*/tests/*');  // 排除测试目录
$scanner->addExcludePattern('*/vendor/*'); // 排除vendor目录
$scanner->addExcludePattern('*.phpt');     // 排除PHPT测试文件

echo "扫描项目中的 PHP 和 C++ 文件（排除测试文件）...\n";

try {
    $files = $scanner->scan(__DIR__ . '/..'); // 扫描项目根目录
    
    echo "找到 " . count($files) . " 个文件:\n";
    
    // 只显示前20个文件以避免输出过多
    $count = 0;
    foreach ($files as $index => $file) {
        if ($count >= 20) {
            echo "... 还有 " . (count($files) - $count) . " 个文件\n";
            break;
        }
        echo ($index + 1) . ". " . $file . "\n";
        $count++;
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
