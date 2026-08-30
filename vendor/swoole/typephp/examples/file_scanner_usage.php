<?php

require_once __DIR__ . '/../vendor/autoload.php'; // 添加自动加载器
require_once __DIR__ . '/../src/Build/FileScanner.php';

use TypePhp\Build\FileScanner;

echo "测试 FileScanner 类...\n";

try {
    // 创建文件扫描器实例，扫描项目根目录
    $scanner = new FileScanner(__DIR__ . '/..', ['.php', '.cc', '.cpp', '.cxx', '.c', '.h', '.hpp']);
    
    // 添加排除模式
    $scanner->addExcludePattern('*/tests/*');  // 排除测试目录
    $scanner->addExcludePattern('*/vendor/*'); // 排除vendor目录
    
    echo "扫描目录: " . $scanner->getDirectory() . "\n";
    
    // 扫描文件
    $files = $scanner->scan();
    
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
    $result = $scanner->scanWithStats();
    
    echo "总共找到 {$result['count']} 个文件\n";
    echo "按扩展名统计:\n";
    foreach ($result['stats'] as $ext => $count) {
        echo "  $ext: $count 个文件\n";
    }
    
    // 测试只扫描 PHP 文件
    echo "\n只扫描 PHP 文件:\n";
    $phpScanner = new FileScanner(__DIR__ . '/..', ['.php']);
    $phpFiles = $phpScanner->scan();
    echo "找到 " . count($phpFiles) . " 个 PHP 文件\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}