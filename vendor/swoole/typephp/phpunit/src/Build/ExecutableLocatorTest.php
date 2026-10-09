<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhpTest\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\Build\ExecutableLocator;

/**
 * @internal
 * @coversNothing
 */
final class ExecutableLocatorTest extends TestCase
{
    private string|false $originalPath;
    private string $directory;

    protected function setUp(): void
    {
        $this->originalPath = getenv('PATH');
        $this->directory = sys_get_temp_dir() . '/typephp-executable-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        if ($this->originalPath === false) {
            putenv('PATH');
        } else {
            putenv('PATH=' . $this->originalPath);
        }

        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testResolvesDirectExecutablePath(): void
    {
        $executable = $this->createExecutable('tpc');

        self::assertSame(realpath($executable), ExecutableLocator::resolve($executable));
    }

    public function testResolvesExecutableFromPath(): void
    {
        $executable = $this->createExecutable('tpc');
        putenv('PATH=' . $this->directory);

        self::assertSame(realpath($executable), ExecutableLocator::resolve('tpc'));
    }

    public function testReturnsNullForUnknownExecutable(): void
    {
        putenv('PATH=' . $this->directory);

        self::assertNull(ExecutableLocator::resolve('missing-tpc'));
    }

    private function createExecutable(string $name): string
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, '#!/bin/sh');
        chmod($path, 0755);
        return $path;
    }
}
