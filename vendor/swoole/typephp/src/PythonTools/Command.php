<?php

namespace TypePhp\PythonTools;

use RuntimeException;
use Throwable;
use TypePhp\PythonTools\Converter\PythonToTypePhpConverter;
use TypePhp\PythonTools\IdeHelper\HelperRenderer;
use TypePhp\PythonTools\IdeHelper\PhpyModuleScanner;
use TypePhp\PythonTools\IdeHelper\PyObjectHelperRenderer;

final class Command
{
    public const GENERATE_HELPER = '--gen-python-helper';
    public const CONVERT_SOURCE = '--convert-python-to-php';

    /** Return null for a normal compiler invocation, otherwise an exit status. */
    public static function execute(array $argv, ?string $workingDirectory = null): ?int
    {
        $helperIndex = array_search(self::GENERATE_HELPER, $argv, true);
        $converterIndex = array_search(self::CONVERT_SOURCE, $argv, true);
        if ($helperIndex === false && $converterIndex === false) {
            return null;
        }
        if ($helperIndex !== false && $converterIndex !== false) {
            return self::error('Python tool subcommands cannot be combined');
        }

        try {
            if ($helperIndex !== false) {
                $root = $workingDirectory ?? getcwd();
                if (!is_string($root) || $root === '') {
                    throw new RuntimeException('Unable to determine the working directory');
                }
                [$module, $outputDirectory] = self::helperArguments($argv, $helperIndex, $root);
                $scanner = new PhpyModuleScanner();
                $renderer = new HelperRenderer();
                $metadata = $scanner->scan($module);
                $relative = str_replace('.', DIRECTORY_SEPARATOR, $module) . '.php';
                $file = $outputDirectory . DIRECTORY_SEPARATOR . 'python' . DIRECTORY_SEPARATOR . $relative;
                $pyObjectFile = $outputDirectory . DIRECTORY_SEPARATOR . 'PyObject.php';
                if (!is_file($pyObjectFile)) {
                    self::writeFile($pyObjectFile, (new PyObjectHelperRenderer())->render());
                    fwrite(STDOUT, "Generated PyObject IDE helper: {$pyObjectFile}" . PHP_EOL);
                }
                $builtins = $module === 'builtins' ? $metadata : $scanner->scan('builtins');
                $builtinsFile = $outputDirectory . DIRECTORY_SEPARATOR . 'python.php';
                self::writeFile($builtinsFile, $renderer->render($builtins));
                fwrite(STDOUT, "Generated Python builtins IDE helper: {$builtinsFile}" . PHP_EOL);
                if ($module !== 'builtins') {
                    self::writeFile($file, $renderer->render($metadata));
                    fwrite(STDOUT, "Generated Python IDE helper: {$file}" . PHP_EOL);
                }
                return 0;
            }

            $file = self::singleArgument($argv, $converterIndex, self::CONVERT_SOURCE, '[your_file.py]');
            fwrite(STDOUT, (new PythonToTypePhpConverter())->convertFile($file));
            return 0;
        } catch (Throwable $exception) {
            return self::error($exception->getMessage());
        }
    }

    /** @return array{string, string} */
    private static function helperArguments(array $argv, int $optionIndex, string $root): array
    {
        $module = $argv[$optionIndex + 1] ?? '';
        if (!is_string($module) || $module === '' || str_starts_with($module, '-')) {
            throw new RuntimeException("Usage: {$argv[0]} " . self::GENERATE_HELPER
                . ' [Python Module] [--output-dir <directory>]');
        }

        $output = null;
        for ($index = 1, $count = count($argv); $index < $count; $index++) {
            $argument = $argv[$index];
            if ($index === $optionIndex || $index === $optionIndex + 1) {
                continue;
            }
            if ($argument === '--output-dir') {
                if ($output !== null || !isset($argv[$index + 1]) || $argv[$index + 1] === '') {
                    throw new RuntimeException('--output-dir requires exactly one directory');
                }
                $output = $argv[++$index];
                continue;
            }
            if (str_starts_with($argument, '--output-dir=')) {
                if ($output !== null) {
                    throw new RuntimeException('--output-dir may only be specified once');
                }
                $output = substr($argument, strlen('--output-dir='));
                if ($output === '') {
                    throw new RuntimeException('--output-dir requires exactly one directory');
                }
                continue;
            }
            throw new RuntimeException("Unknown argument for " . self::GENERATE_HELPER . ": {$argument}");
        }

        if ($output === null) {
            return [$module, $root . DIRECTORY_SEPARATOR . 'ide-helper'];
        }
        if (self::isAbsolutePath($output)) {
            $absoluteOutput = rtrim($output, '/\\');
            return [$module, $absoluteOutput === '' ? DIRECTORY_SEPARATOR : $absoluteOutput];
        }
        return [$module, $root . DIRECTORY_SEPARATOR . rtrim($output, '/\\')];
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/D', $path) === 1;
    }

    private static function singleArgument(array $argv, int $optionIndex, string $option, string $placeholder): string
    {
        $arguments = [];
        foreach (array_slice($argv, 1) as $argument) {
            if ($argument !== $option) {
                $arguments[] = $argument;
            }
        }
        if (count($arguments) !== 1 || $arguments[0] === '' || str_starts_with($arguments[0], '-')) {
            throw new RuntimeException("Usage: {$argv[0]} {$option} {$placeholder}");
        }
        if (!isset($argv[$optionIndex + 1]) || $argv[$optionIndex + 1] !== $arguments[0]) {
            throw new RuntimeException("{$option} must be followed by {$placeholder}");
        }
        return $arguments[0];
    }

    private static function writeFile(string $file, string $contents): void
    {
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create IDE helper directory: {$directory}");
        }
        $temporary = $file . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($temporary, $contents) === false || !rename($temporary, $file)) {
            @unlink($temporary);
            throw new RuntimeException("Unable to write IDE helper: {$file}");
        }
    }

    private static function error(string $message): int
    {
        fwrite(STDERR, "\033[31mError: {$message}\033[0m" . PHP_EOL);
        return 1;
    }
}
