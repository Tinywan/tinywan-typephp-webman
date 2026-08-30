#!/usr/bin/env php
<?php
/**
 * This file is part of Swoole-Compiler(AOT).
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

declare(strict_types=1);

use TypePhp\Testing\TestCoverageAnalyzer;

require __DIR__ . '/bootstrap.php';

$format = 'summary';
$output = null;
$includePhpUnit = true;
$strict = false;
$phpVersions = ['8.4', '8.5'];
$paths = [];

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        printUsage($argv[0]);
        exit(0);
    }
    if ($argument === '--no-phpunit') {
        $includePhpUnit = false;
        continue;
    }
    if ($argument === '--strict') {
        $strict = true;
        continue;
    }
    if (str_starts_with($argument, '--format=')) {
        $format = substr($argument, strlen('--format='));
        continue;
    }
    if (str_starts_with($argument, '--output=')) {
        $output = substr($argument, strlen('--output='));
        continue;
    }
    if (str_starts_with($argument, '--php-versions=')) {
        $phpVersions = array_values(array_filter(array_map('trim', explode(',', substr($argument, strlen('--php-versions='))))));
        continue;
    }
    if (str_starts_with($argument, '-')) {
        fwrite(STDERR, 'Unknown option: ' . $argument . PHP_EOL);
        exit(2);
    }
    $paths[] = $argument;
}

if (!in_array($format, ['summary', 'json', 'markdown'], true)) {
    fwrite(STDERR, 'Invalid format. Expected summary, json or markdown.' . PHP_EOL);
    exit(2);
}
if ($phpVersions === []) {
    fwrite(STDERR, 'At least one target PHP version is required.' . PHP_EOL);
    exit(2);
}
foreach ($phpVersions as $version) {
    if (!preg_match('/^\d+\.\d+$/', $version)) {
        fwrite(STDERR, 'Invalid PHP version: ' . $version . PHP_EOL);
        exit(2);
    }
}
if ($paths === []) {
    $paths = ['tests/compiler'];
}

try {
    $analyzer = new TestCoverageAnalyzer(TYPEPHP_ROOT_PATH, $phpVersions);
    $report = $analyzer->analyze(
        $paths,
        $includePhpUnit ? TYPEPHP_ROOT_PATH . '/phpunit/src' : null,
        $includePhpUnit ? TYPEPHP_ROOT_PATH . '/phpunit/code' : null,
    );
} catch (Throwable $error) {
    fwrite(STDERR, 'Coverage analysis failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

$rendered = match ($format) {
    'summary' => $analyzer->renderSummary($report),
    'markdown' => $analyzer->renderMarkdown($report),
    'json' => json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL,
};

if ($output === null) {
    echo $rendered;
} else {
    $outputPath = isAbsolutePath($output) ? $output : TYPEPHP_ROOT_PATH . DIRECTORY_SEPARATOR . $output;
    $directory = dirname($outputPath);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        fwrite(STDERR, 'Unable to create output directory: ' . $directory . PHP_EOL);
        exit(1);
    }
    if (file_put_contents($outputPath, $rendered) === false) {
        fwrite(STDERR, 'Unable to write report: ' . $outputPath . PHP_EOL);
        exit(1);
    }
    echo 'Wrote ', $format, ' coverage report: ', relativePath(TYPEPHP_ROOT_PATH, $outputPath), PHP_EOL;
}

if ($strict && ($report['parse_errors'] !== [] || $report['unresolved_phpunit_fixtures'] !== [])) {
    exit(1);
}

function printUsage(string $script): void
{
    echo <<<USAGE
Usage:
  php {$script} [options] [PHPT path ...]

Options:
  --format=summary|json|markdown  Output format (default: summary)
  --output=<file>                Write the report to a file
  --php-versions=8.4,8.5         Target PHP version columns
  --no-phpunit                   Do not scan PHPUnit compiler fixtures
  --strict                       Fail on parse issues or unresolved fixture links
  -h, --help                     Show this help

Examples:
  php {$script}
  php {$script} --format=markdown --output=build/test-coverage.md
  php {$script} --format=json tests/compiler/type_decl tests/compiler/basic

The tool reports separate, explicitly denominated AST-node, positive compile,
runtime semantic and negative diagnostic coverage. It never emits a combined
overall percentage.

USAGE;
}

function isAbsolutePath(string $path): bool
{
    return $path !== '' && ($path[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1);
}

function relativePath(string $root, string $path): string
{
    $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
}
