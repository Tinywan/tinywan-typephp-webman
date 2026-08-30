<?php

require_once __DIR__ . '/../src/Build/FileScanner.php';

use TypePhp\Build\FileScanner;

echo "测试基本扫描功能...\n";
$scanner = new FileScanner(__DIR__ . '/..', ['.php', '.cc', '.cpp', '.cxx', '.c', '.h', '.hpp']);
$allFiles = $scanner->scan();
echo "所有文件数量: " . count($allFiles) . "\n";

echo "\n测试排除模式...\n";
$scannerWithExclusions = new FileScanner(__DIR__ . '/..', ['.php', '.cc', '.cpp', '.cxx', '.c', '.h', '.hpp']);
$scannerWithExclusions->addExcludePattern('*/tests/*');  // 排除测试目录
$filteredFiles = $scannerWithExclusions->scan();
echo "排除测试目录后的文件数量: " . count($filteredFiles) . "\n";

// 检查结果中是否确实没有包含 tests 目录的文件
$testFilesFound = 0;
foreach ($filteredFiles as $file) {
    if (strpos($file, '/tests/') !== false) {
        $testFilesFound++;
    }
}

echo "在过滤后的结果中找到 $testFilesFound 个在 tests 目录中的文件\n";

// 显示一些过滤后的文件
echo "\n前10个过滤后的文件:\n";
$counter = 0;
foreach ($filteredFiles as $file) {
    if ($counter >= 10) break;
    echo ($counter + 1) . ". " . $file . "\n";
    $counter++;
}