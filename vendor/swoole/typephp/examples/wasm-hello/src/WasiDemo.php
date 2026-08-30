<?php

declare(strict_types=1);

use native_types;

final class WasiDemo
{
    public static function report(array $arguments, string $greeting, string $stdin): array
    {
        if ($greeting === '') {
            $greeting = 'Hello from the WASI environment';
        }

        $argv = array_merge(['typephp.wasm'], $arguments);

        return [
            'runtime' => [
                'php' => phpversion(),
                'platform' => php_uname(),
                'integerBits' => PHP_INT_SIZE * 8,
                'extensions' => get_loaded_extensions(),
            ],
            'clock' => [
                'timestamp' => time(),
                'iso8601' => date('Y-m-d H:i:s T'),
                'microtime' => microtime(true),
            ],
            'random' => [
                'integer' => random_int(100000, 999999),
                'token' => bin2hex(random_bytes(8)),
            ],
            'input' => [
                'argc' => count($argv),
                'argv' => $argv,
                'greeting' => $greeting,
                'stdin' => trim($stdin),
            ],
            'filesystem' => self::filesystemReport(),
            'http' => self::httpReport(),
            'precision' => self::precisionReport(),
            'capabilities' => [
                'supported' => ['arguments', 'environment', 'stdin/stdout/stderr', 'clock', 'random', 'filesystem', 'HTTP GET'],
                'disabled' => ['raw sockets', 'process', 'signals', 'shell', 'Fiber', 'Generator'],
            ],
        ];
    }

    public static function extensionInfo(string $name): array
    {
        if (!extension_loaded($name)) {
            throw new InvalidArgumentException("PHP extension '{$name}' is not loaded");
        }

        $extension = new ReflectionExtension($name);
        $functions = get_extension_funcs($name);
        if ($functions === false) {
            $functions = [];
        }

        return [
            'name' => $extension->getName(),
            'version' => $extension->getVersion() ?: 'built-in',
            'persistent' => $extension->isPersistent(),
            'temporary' => $extension->isTemporary(),
            'dependencies' => $extension->getDependencies(),
            'iniEntries' => $extension->getINIEntries(),
            'constants' => array_keys($extension->getConstants()),
            'functions' => array_values($functions),
            'classes' => $extension->getClassNames(),
        ];
    }

    private static function httpReport(): array
    {
        $url = getenv('TYPEPHP_FETCH_URL');
        if ($url === false || $url === '') {
            return ['ok' => false, 'url' => '', 'bytes' => 0, 'preview' => 'No URL configured'];
        }

        $body = file_get_contents($url);
        if ($body === false) {
            return ['ok' => false, 'url' => $url, 'bytes' => 0, 'preview' => 'Request failed'];
        }

        return [
            'ok' => true,
            'url' => $url,
            'bytes' => strlen($body),
            'preview' => trim(substr($body, 0, 80)),
        ];
    }

    private static function filesystemReport(): array
    {
        $directory = '/workspace';
        if (!is_dir($directory)) {
            mkdir($directory);
        }

        $counterFile = $directory . '/run-count.txt';
        $counter = 0;
        if (file_exists($counterFile)) {
            $counter = (int) trim((string) file_get_contents($counterFile));
        }
        $counter++;
        file_put_contents($counterFile, (string) $counter);

        $messageFile = $directory . '/hello.txt';
        $message = 'TypePHP wrote this file during browser run #' . $counter;
        file_put_contents($messageFile, $message);

        $files = scandir($directory);
        if ($files === false) {
            $files = [];
        }
        $visibleFiles = array_values(array_diff($files, ['.', '..']));

        return [
            'run' => $counter,
            'readback' => (string) file_get_contents($messageFile),
            'files' => $visibleFiles,
        ];
    }

    private static function precisionReport(): array
    {
        $big = std::bigInt('123456789012345678901234567890');
        $bigResult = ($big * std::bigInt(1000000) + std::bigInt(42))->toString();

        $price = std::decimal('199.95');
        $taxRate = std::decimal('0.0825');
        $decimalResult = ($price * (std::decimal(1) + $taxRate))->toString();

        $pi = std::bigFloat('3.141592653589793238462643383279502884197');
        $radius = std::bigFloat(12);
        $bigFloatResult = ($pi * $radius * $radius)->toString();

        return [
            'bigint' => $bigResult,
            'decimal' => $decimalResult,
            'bigfloat' => $bigFloatResult,
        ];
    }
}
