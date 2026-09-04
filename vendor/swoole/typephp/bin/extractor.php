<?php
require __DIR__ . '/bootstrap.php';
// ============================================================================
// Command-line interface
// ============================================================================

function showUsage(): void
{
    echo <<<USAGE
用法: php extract_functions.php <file.cpp> [prefix] [output.json]

参数:
  file.cpp      要分析的 C/C++ 文件（必需）
  prefix        函数名前缀，多个前缀用逗号分隔（默认: php_）
  output.json   输出文件路径（默认: stdout）

选项:
  --help, -h    显示此帮助信息
  --pretty      美化 JSON 输出
  --batch       批量处理模式（从 stdin 读取文件列表）

示例:
  # 基本用法
  php extract_functions.php myfile.cpp

  # 指定前缀
  php extract_functions.php myfile.cpp php_

  # 多个前缀
  php extract_functions.php myfile.cpp php_,swoole_,zend_

  # 保存到文件
  php extract_functions.php myfile.cpp php_ output.json

  # 美化输出
  php extract_functions.php myfile.cpp php_ output.json --pretty

  # 批量处理
  find . -name "*.cpp" | php extract_functions.php --batch php_

特点:
  ✓ 无需头文件
  ✓ 速度快
  ✓ 支持 C/C++
  ✓ 输出 JSON 格式
  ✓ 支持复杂的参数类型

USAGE;
}

function extractorMain(array $argv): void
{
    // Parse command-line arguments
    $options = [
        'help' => false,
        'pretty' => false,
        'batch' => false,
    ];

    $args = [];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        if ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
        } elseif ($arg === '--pretty') {
            $options['pretty'] = true;
        } elseif ($arg === '--batch') {
            $options['batch'] = true;
        } else {
            $args[] = $arg;
        }
    }

    // Show help
    if ($options['help'] || (empty($args) && !$options['batch'])) {
        showUsage();
        exit(0);
    }

    // Create the extractor
    $extractor = new TypePhp\Extractor();

    // Batch mode
    if ($options['batch']) {
        $prefixes = !empty($args[0]) ? explode(',', $args[0]) : ['php_'];
        $files = [];

        while ($line = fgets(STDIN)) {
            $file = trim($line);
            if (!empty($file) && file_exists($file)) {
                $files[] = $file;
            }
        }

        if (empty($files)) {
            fprintf(STDERR, "错误: 未找到有效的文件\n");
            exit(1);
        }

        $functions = $extractor->extractFromFiles($files, $prefixes);

        // Output the result
        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($options['pretty']) {
            $jsonFlags |= JSON_PRETTY_PRINT;
        }

        echo json_encode($functions, $jsonFlags) . "\n";
        exit(0);
    }

    // Single-file mode
    $filename = $args[0] ?? null;
    $prefixesStr = $args[1] ?? 'php_';
    $output = $args[2] ?? null;

    if (!$filename) {
        fprintf(STDERR, "错误: 未指定文件\n");
        showUsage();
        exit(1);
    }

    // Parse prefixes
    $prefixes = array_map('trim', explode(',', $prefixesStr));

    try {
        // Extract functions
        $functions = $extractor->extractFunctions($filename, $prefixes);

        // Prepare JSON output
        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($options['pretty']) {
            $jsonFlags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($functions, $jsonFlags);

        // Output
        if ($output) {
            file_put_contents($output, $json . "\n");
            fprintf(STDERR, "\033[0;32m已保存到: %s\033[0m\n", $output);
        } else {
            echo $json . "\n";
        }

    } catch (Exception $e) {
        fprintf(STDERR, "\033[0;31m错误: %s\033[0m\n", $e->getMessage());
        exit(1);
    }
}

// Run the main function.
if (php_sapi_name() === 'cli') {
    extractorMain($argv);
}
