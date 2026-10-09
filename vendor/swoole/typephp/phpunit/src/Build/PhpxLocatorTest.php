<?php

namespace TypePhpTest\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\Build\PhpxLocator;

final class PhpxLocatorTest extends TestCase
{
    private string|false $originalPhpxHome;
    private string $phpxHome;

    protected function setUp(): void
    {
        $this->originalPhpxHome = getenv('PHPX_HOME');
        $this->phpxHome = sys_get_temp_dir() . '/typephp-phpx-locator-' . bin2hex(random_bytes(6));
        mkdir($this->phpxHome, 0777, true);
    }

    protected function tearDown(): void
    {
        if ($this->originalPhpxHome === false) {
            putenv('PHPX_HOME');
        } else {
            putenv('PHPX_HOME=' . $this->originalPhpxHome);
        }
        $this->removeDirectory($this->phpxHome);
    }

    public function testPhpxHomeHasPriorityAndReturnsAnAbsolutePath(): void
    {
        putenv('PHPX_HOME=' . $this->phpxHome);

        self::assertSame(realpath($this->phpxHome), PhpxLocator::resolve('/not-used'));
    }

    public function testInvalidPhpxHomeFallsBackToComposerInstallation(): void
    {
        putenv('PHPX_HOME=' . $this->phpxHome . '/missing');
        $projectRoot = dirname(__DIR__, 3);

        self::assertSame(
            realpath($projectRoot . '/vendor/swoole/phpx'),
            PhpxLocator::resolve($projectRoot),
        );
    }

    public function testCompilerLocalInstallationPrecedesComposerMetadata(): void
    {
        putenv('PHPX_HOME=' . $this->phpxHome . '/missing');
        $projectRoot = $this->phpxHome . '/compiler';
        $localPhpx = $projectRoot . '/vendor/swoole/phpx';
        mkdir($localPhpx, 0777, true);

        self::assertSame(realpath($localPhpx), PhpxLocator::resolve($projectRoot));
    }

    private function removeDirectory(string $directory): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
