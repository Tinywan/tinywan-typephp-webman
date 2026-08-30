<?php

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;
use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

class WasiUnsupportedSyntaxTest extends TestCase
{
    /** @dataProvider unsupportedConversionSyntaxProvider */
    public function testUnsupportedSyntaxFailsDuringWasiConversion(string $file, string $message): void
    {
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        (new \ReflectionClass($compiler))->getProperty('targetPlatform')->setValue($compiler, 'wasm32-wasip2');
        $source = __DIR__ . '/../code/' . $file;
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage($message);
        $compiler->convertFile($source);
    }

    public static function unsupportedConversionSyntaxProvider(): array
    {
        return [
            'backtick shell execution' => [
                'wasi-backtick.php',
                'Backtick shell execution is not supported by the WASI target',
            ],
            'generator closure' => [
                'wasi-generator-closure.php',
                'Fiber and Generator are not supported by the WASI target',
            ],
            'generator arrow function' => [
                'wasi-generator-arrow.php',
                'Fiber and Generator are not supported by the WASI target',
            ],
            'process function' => [
                'wasi-process.php',
                'Function `proc_open` is not supported by the WASI target',
            ],
            'socket function' => [
                'wasi-socket.php',
                'Function `stream_socket_server` is not supported by the WASI target',
            ],
            'signal function' => [
                'wasi-signal.php',
                'Function `pcntl_signal` is not supported by the WASI target',
            ],
        ];
    }
}
