<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Build/FileScanner.php';

use TypePhp\Build\FileScanner;

echo "测试使用 PHP-Parser 的语法检查功能...\n";

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
        $localVar = "world";  // 这在函数内部，是允许的
        return $localVar;
    }
}

function testFunction() {
    $localVar = "hello";  // 这在函数内部，是允许的
    return $localVar;
}

interface TestInterface {
    public function interfaceMethod();
}

trait TestTrait {
    public function traitMethod() {
        return "trait";
    }
}
');

// 创建一个包含执行逻辑的 PHP 文件
$executableFile = $testDir . '/executable.php';
file_put_contents($executableFile, '<?php
namespace Test;

// 这些是全局作用域的执行逻辑
$var = "hello";
echo $var;
$result = testFunction(); // 函数调用

class TestClass {
    public function testMethod() {
        $localVar = "world";  // 这在函数内部，是允许的
        return $localVar;
    }
}

function testFunction() {
    return 42;
}

// 这也是全局作用域的执行逻辑
if (true) {
    echo "conditional execution";
}
');

// 创建一个包含 require 但无其他执行逻辑的文件
$requireFile = $testDir . '/with_require.php';
file_put_contents($requireFile, '<?php
namespace Test;

require_once "some_file.php";  // require 在顶层是允许的

class TestClass {
    public function testMethod() {
        return "hello";
    }
}

function testFunction() {
    return 42;
}
');

echo "测试目录: $testDir\n";

try {
    // 测试只扫描定义文件
    echo "\n扫描只包含定义的 PHP 文件:\n";
    $scanner = new FileScanner($testDir, ['.php'], true); // 启用语法检查
    $definitionFiles = $scanner->scan();
    
    echo "找到 " . count($definitionFiles) . " 个有效定义文件:\n";
    foreach ($definitionFiles as $file) {
        $basename = basename($file);
        echo "  - $basename\n";
    }
    
    // 测试扫描所有 PHP 文件（不检查语法）
    echo "\n扫描所有 PHP 文件（不检查语法）:\n";
    $scanner2 = new FileScanner($testDir, ['.php'], false); // 不启用语法检查
    $allFiles = $scanner2->scan();
    
    echo "找到 " . count($allFiles) . " 个文件:\n";
    foreach ($allFiles as $file) {
        $basename = basename($file);
        echo "  - $basename\n";
    }
    
    echo "\n预期结果：\n";
    echo "- definitions.php 应该被包含（只包含定义）\n";
    echo "- with_require.php 应该被包含（require 是允许的）\n";
    echo "- executable.php 不应该被包含（包含全局执行逻辑）\n";
    
    // 清理测试文件
    unlink($definitionFile);
    unlink($executableFile);
    unlink($requireFile);
    rmdir($testDir);
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    
    // 清理测试文件（如果出错）
    if (file_exists($definitionFile)) unlink($definitionFile);
    if (file_exists($executableFile)) unlink($executableFile);
    if (file_exists($requireFile)) unlink($requireFile);
    if (is_dir($testDir)) rmdir($testDir);
}