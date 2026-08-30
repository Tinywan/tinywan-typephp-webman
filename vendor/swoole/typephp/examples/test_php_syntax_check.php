<?php

require_once __DIR__ . '/../src/Build/FileScanner.php';

use TypePhp\Build\FileScanner;

echo "测试 PHP 语法检查功能...\n";

// 创建一个测试用的临时目录和文件
$testDir = __DIR__ . '/test_temp';
if (!is_dir($testDir)) {
    mkdir($testDir);
}

// 创建一个只包含定义的 PHP 文件
$definitionFile = $testDir . '/definitions.php';
file_put_contents($definitionFile, '<?php
namespace Test;

class TestClass {
    public function testMethod() {
        return "hello";
    }
}

function testFunction() {
    return 42;
}

interface TestInterface {
    public function interfaceMethod();
}
');

// 创建一个包含执行逻辑的 PHP 文件
$executableFile = $testDir . '/executable.php';
file_put_contents($executableFile, '<?php
namespace Test;

$var = "hello";  // 这是执行逻辑
echo $var;       // 这也是执行逻辑

class TestClass {
    public function testMethod() {
        $localVar = "world";  // 这在函数内部，是允许的
        return $localVar;
    }
}

$globalVar = testFunction(); // 这是执行逻辑
');

echo "测试目录: $testDir\n";

try {
    // 测试只扫描定义文件
    echo "\n扫描只包含定义的 PHP 文件:\n";
    $scanner = new FileScanner($testDir, ['.php'], true); // 启用语法检查
    $definitionFiles = $scanner->scan();
    
    echo "找到 " . count($definitionFiles) . " 个有效定义文件:\n";
    foreach ($definitionFiles as $file) {
        echo "  - $file\n";
    }
    
    // 测试扫描所有 PHP 文件（不检查语法）
    echo "\n扫描所有 PHP 文件（不检查语法）:\n";
    $scanner2 = new FileScanner($testDir, ['.php'], false); // 不启用语法检查
    $allFiles = $scanner2->scan();
    
    echo "找到 " . count($allFiles) . " 个文件:\n";
    foreach ($allFiles as $file) {
        echo "  - $file\n";
    }
    
    // 清理测试文件
    unlink($definitionFile);
    unlink($executableFile);
    rmdir($testDir);
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    
    // 清理测试文件（如果出错）
    if (file_exists($definitionFile)) unlink($definitionFile);
    if (file_exists($executableFile)) unlink($executableFile);
    if (is_dir($testDir)) rmdir($testDir);
}