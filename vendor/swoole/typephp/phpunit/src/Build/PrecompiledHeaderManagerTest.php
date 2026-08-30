<?php

namespace TypePhp\Tests\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\Backend\CompilerBackend;
use TypePhp\Build\CompileOptions;
use TypePhp\Build\NativeBuilder;
use TypePhp\Build\PrecompiledHeaderManager;

final class PrecompiledHeaderManagerTest extends TestCase
{
    private string $cacheDirectory;

    protected function setUp(): void
    {
        $this->cacheDirectory = sys_get_temp_dir() . '/typephp_pch_' . bin2hex(random_bytes(8));
        mkdir($this->cacheDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->cacheDirectory);
    }

    public function testPreparePrunesExpiredAndExcessCacheEntries(): void
    {
        $oldDirectory = $this->createCacheEntry(1, time() - 31 * 86400);
        for ($i = 2; $i <= 10; $i++) {
            $this->createCacheEntry($i, time() - (10 - $i));
        }
        $unmanagedDirectory = $this->cacheDirectory . '/keep-me';
        mkdir($unmanagedDirectory);

        $backend = $this->createMock(CompilerBackend::class);
        $backend->method('supportsPrecompiledHeaders')->willReturn(true);
        $backend->method('getName')->willReturn('test');
        $backend->method('getCompilerCommand')->willReturn('true');
        $backend->method('getPrecompiledHeaderArtifact')
            ->willReturnCallback(static fn(string $header): string => $header . '.gch');
        $backend->method('buildNativeCompileCommand')
            ->willReturnCallback(
                static fn(string $source, string $object): string => 'touch ' . escapeshellarg($object),
            );

        $result = (new PrecompiledHeaderManager($backend, new NativeBuilder($backend)))->prepare(
            ['cstring', 'phpx.h'],
            [],
            $this->cacheDirectory,
            new CompileOptions([]),
        );

        $this->assertFileExists($result['artifact']);
        $this->assertStringContainsString("#include <cstring>\n#include <phpx.h>\n", file_get_contents($result['header']));
        $this->assertDirectoryDoesNotExist($oldDirectory);
        $this->assertDirectoryExists($unmanagedDirectory);

        $managedEntries = array_filter(
            scandir($this->cacheDirectory),
            static fn(string $entry): bool => preg_match('/^[a-f0-9]{24}$/D', $entry) === 1,
        );
        $this->assertCount(8, $managedEntries);
    }

    private function createCacheEntry(int $number, int $mtime): string
    {
        $directory = $this->cacheDirectory . '/' . sprintf('%024x', $number);
        mkdir($directory);
        file_put_contents($directory . '/typephp_pch.hpp.gch', 'cached');
        touch($directory, $mtime);
        return $directory;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($directory);
    }
}
