#!/usr/bin/env php
<?php

declare(strict_types=1);

const DEFAULT_TEST_DIR = 'tests';

$root = dirname(__DIR__);
$artifactSuffixes = [
    '.php',
    '.exp',
    '.log',
    '.out',
    '.diff',
    '.sh',
    '.mem',
    '.clean.php',
    '.skip.php',
];
$binarySuffix = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';

$dryRun = false;
$paths = [];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run' || $arg === '-n') {
        $dryRun = true;
        continue;
    }
    if ($arg === '--help' || $arg === '-h') {
        printUsage($argv[0]);
        exit(0);
    }
    $paths[] = $arg;
}

if (!$paths) {
    $paths[] = DEFAULT_TEST_DIR;
}

$phptFiles = [];
foreach ($paths as $path) {
    $absolute = resolvePath($root, $path);
    if (is_file($absolute)) {
        if (str_ends_with($absolute, '.phpt')) {
            $phptFiles[$absolute] = true;
        }
        continue;
    }
    if (!is_dir($absolute)) {
        fwrite(STDERR, "Path not found: {$path}\n");
        exit(1);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        $filePath = $file->getPathname();
        if (str_ends_with($filePath, '.phpt')) {
            $phptFiles[$filePath] = true;
        }
    }
}

$artifacts = [];
foreach (array_keys($phptFiles) as $phptFile) {
    $base = substr($phptFile, 0, -5);
    foreach ($artifactSuffixes as $suffix) {
        $artifact = $base . $suffix;
        if (is_file($artifact)) {
            $artifacts[$artifact] = true;
        }
    }

    $binary = $root . DIRECTORY_SEPARATOR . str_replace('-', '_', basename($base)) . $binarySuffix;
    if (is_file($binary) && is_executable($binary)) {
        $artifacts[$binary] = true;
    }
}

$artifacts = array_keys($artifacts);
sort($artifacts);

$deleted = 0;
foreach ($artifacts as $artifact) {
    $displayPath = relativePath($root, $artifact);
    if ($dryRun) {
        echo $displayPath, PHP_EOL;
        continue;
    }
    if (!unlink($artifact)) {
        fwrite(STDERR, "Failed to remove: {$displayPath}\n");
        exit(1);
    }
    ++$deleted;
}

if ($dryRun) {
    printf("Found %d PHPT artifact file(s).\n", count($artifacts));
} else {
    printf("Removed %d PHPT artifact file(s).\n", $deleted);
}

function printUsage(string $script): void
{
    echo <<<USAGE
Usage:
  php {$script} [--dry-run] [path ...]

Examples:
  php {$script}
  php {$script} tests/aot/symfony
  php {$script} --dry-run tests/aot/basic/001.phpt

Only files generated from an existing .phpt are removed. This includes adjacent
.php/.log/.out/.diff files and the AOT test binary generated in the project root.

USAGE;
}

function resolvePath(string $root, string $path): string
{
    if ($path === '') {
        return $root;
    }
    if ($path[0] === '/') {
        return $path;
    }
    return $root . DIRECTORY_SEPARATOR . $path;
}

function relativePath(string $root, string $path): string
{
    $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (str_starts_with($path, $prefix)) {
        return substr($path, strlen($prefix));
    }
    return $path;
}
