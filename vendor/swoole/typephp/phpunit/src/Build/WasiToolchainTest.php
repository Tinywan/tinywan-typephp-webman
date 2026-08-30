<?php

namespace TypePhp\Tests\Build;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypePhp\Build\WasiToolchain;

final class WasiToolchainTest extends TestCase
{
    private string $directory;
    private string|false $originalPath;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/typephp-wasi-tools-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);
        $this->originalPath = getenv('PATH');
    }

    protected function tearDown(): void
    {
        putenv('PATH=' . ($this->originalPath !== false ? $this->originalPath : ''));
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testDetectsSupportedToolsOnlyFromPath(): void
    {
        $this->installFakeTools(22, 47, 1, 'wasm32-unknown-wasip2');
        putenv('PATH=' . $this->directory);

        $tools = (new WasiToolchain())->detect();

        $this->assertSame($this->directory . '/wasm32-wasip2-clang++', $tools['clang++']);
        $this->assertSame($this->directory . '/wasmtime', $tools['wasmtime']);
        $this->assertSame($this->directory . '/jco', $tools['jco']);
        $this->assertSame('wasm32-unknown-wasip2', $tools['target']);
        $this->assertSame('22.0.0', $tools['clang-version']);
        $this->assertSame('47.0.0', $tools['wasmtime-version']);
        $this->assertSame('1.0.0', $tools['jco-version']);
    }

    public function testRejectsMissingTool(): void
    {
        putenv('PATH=' . $this->directory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('`wasm32-wasip2-clang` was not found in PATH');
        (new WasiToolchain())->detect();
    }

    public function testComponentOnlyProfileDoesNotRequireJco(): void
    {
        $this->installFakeTools(22, 47, 1, 'wasm32-unknown-wasip2');
        unlink($this->directory . '/jco');
        putenv('PATH=' . $this->directory);

        $tools = (new WasiToolchain())->detect(false);

        $this->assertArrayNotHasKey('jco', $tools);
        $this->assertArrayNotHasKey('jco-version', $tools);
        $this->assertSame('wasm32-unknown-wasip2', $tools['target']);
    }

    public function testLibraryModeRequiresPinnedWitBindgenFromPath(): void
    {
        $this->installFakeTools(22, 47, 1, 'wasm32-unknown-wasip2');
        putenv('PATH=' . $this->directory);

        $tools = (new WasiToolchain())->detect(false, true);

        $this->assertSame($this->directory . '/wit-bindgen', $tools['wit-bindgen']);
        $this->assertSame('0.60.0', $tools['wit-bindgen-version']);
    }

    public function testLibraryModeRejectsIncompatibleWitBindgen(): void
    {
        $this->installFakeTools(22, 47, 1, 'wasm32-unknown-wasip2');
        $this->writeExecutable('wit-bindgen', "#!/bin/sh\necho 'wit-bindgen-cli 0.61.0'\n");
        putenv('PATH=' . $this->directory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('wit-bindgen-cli 0.60.0 is required');
        (new WasiToolchain())->detect(false, true);
    }

    public function testRejectsOldLlvm(): void
    {
        $this->installFakeTools(21, 47, 1, 'wasm32-unknown-wasip2');
        putenv('PATH=' . $this->directory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('`wasm32-wasip2-clang` 21 is too old');
        (new WasiToolchain())->detect();
    }

    public function testRejectsNonWasiClangTarget(): void
    {
        $this->installFakeTools(22, 47, 1, 'x86_64-unknown-linux-gnu');
        putenv('PATH=' . $this->directory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not configured for wasm32-unknown-wasip2');
        (new WasiToolchain())->detect();
    }

    private function installFakeTools(int $llvmMajor, int $wasmtimeMajor, int $jcoMajor, string $target): void
    {
        foreach (['wasm32-wasip2-clang', 'llvm-ar', 'llvm-ranlib', 'llvm-nm'] as $tool) {
            $this->writeExecutable($tool, "#!/bin/sh\necho 'LLVM version {$llvmMajor}.0.0'\n");
        }
        $this->writeExecutable('wasm-component-ld', "#!/bin/sh\necho 'wasm-component-ld version 0.5.22'\n");
        $this->writeExecutable(
            'wasm32-wasip2-clang++',
            "#!/bin/sh\nif [ \"\$1\" = '--print-target-triple' ]; then echo '{$target}'; else echo 'clang version {$llvmMajor}.0.0'; fi\n",
        );
        $this->writeExecutable('wasmtime', "#!/bin/sh\necho 'wasmtime {$wasmtimeMajor}.0.0'\n");
        $this->writeExecutable('jco', "#!/bin/sh\necho 'jco {$jcoMajor}.0.0'\n");
        $this->writeExecutable('wit-bindgen', "#!/bin/sh\necho 'wit-bindgen-cli 0.60.0'\n");
    }

    private function writeExecutable(string $name, string $contents): void
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, $contents);
        chmod($path, 0755);
    }
}
