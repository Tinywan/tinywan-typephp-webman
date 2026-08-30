<?php

// 测试 fnmatch 函数的行为
$testPath = '/home/swoole/workspace/aot/tests/aot/strlen.phpt';
$pattern = '*/tests/*';

echo "测试路径: $testPath\n";
echo "模式: $pattern\n";
echo "fnmatch 匹配结果: " . (fnmatch($pattern, $testPath, FNM_PATHNAME) ? 'true' : 'false') . "\n";
echo "使用 FNM_PATHNAME 标志: " . (fnmatch($pattern, $testPath, FNM_PATHNAME) ? 'true' : 'false') . "\n";

// 尝试相对路径
$basePath = '/home/swoole/workspace/aot';
$relativePath = str_replace($basePath . '/', '', $testPath);
echo "相对路径: $relativePath\n";
echo "相对路径 fnmatch 匹配结果: " . (fnmatch($pattern, $relativePath, FNM_PATHNAME) ? 'true' : 'false') . "\n";

// 测试更具体的模式
$pattern2 = '*tests*';
echo "模式2: $pattern2\n";
echo "fnmatch 匹配结果: " . (fnmatch($pattern2, $testPath, FNM_PATHNAME) ? 'true' : 'false') . "\n";