<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Tests\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\Build\AtomicFile;

/**
 * @internal
 * @coversNothing
 */
final class AtomicFileTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/typephp-atomic-file-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->directory);
    }

    public function testWriteCreatesDirectoriesAndReplacesExistingContents(): void
    {
        $path = $this->directory . '/nested/state.json';

        AtomicFile::write($path, 'first', '.state-');
        AtomicFile::write($path, 'second', '.state-');

        self::assertSame('second', file_get_contents($path));
        self::assertSame([$path], glob($this->directory . '/nested/*'));
    }

    public function testMissingReplacementDoesNotRemoveExistingTarget(): void
    {
        $target = $this->directory . '/state.json';
        AtomicFile::write($target, 'stable');

        try {
            AtomicFile::replace($this->directory . '/missing.tmp', $target);
            self::fail('Missing replacement must be rejected');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('does not exist', $error->getMessage());
        }

        self::assertSame('stable', file_get_contents($target));
    }
}
