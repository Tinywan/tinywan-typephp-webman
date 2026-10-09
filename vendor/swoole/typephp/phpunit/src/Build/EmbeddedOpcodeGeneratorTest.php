<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Tests\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\Build\EmbeddedOpcodeGenerator;

/**
 * @internal
 * @coversNothing
 */
final class EmbeddedOpcodeGeneratorTest extends TestCase
{
    private string $directory;
    private string $buildDirectory;
    private string $vendorFile;
    private string $anonymousFile;
    private string $ordinaryFile;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/typephp-opcode-generator-' . bin2hex(random_bytes(6));
        $this->buildDirectory = $this->directory . '/build';
        $vendor = $this->directory . '/vendor';
        mkdir($vendor . '/package', 0777, true);
        file_put_contents($vendor . '/autoload.php', '<?php');
        $this->vendorFile = $vendor . '/package/library.php';
        $this->anonymousFile = $this->directory . '/anonymous.php';
        $this->ordinaryFile = $this->directory . '/ordinary.php';
        file_put_contents($this->vendorFile, '<?php return 1;');
        file_put_contents($this->anonymousFile, '<?php return new class {};');
        file_put_contents($this->ordinaryFile, '<?php return 2;');
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->directory);
    }

    public function testPrepareReusesOnlyVendorAndAnonymousCaches(): void
    {
        $files = [$this->vendorFile, $this->anonymousFile, $this->ordinaryFile];
        $generator = $this->generator();
        $first = $generator->prepare($files, [$this->anonymousFile => '@typephp:anon:test']);

        self::assertSame($files, $first->pendingFiles());
        self::assertTrue($first->isVendorFile($this->vendorFile));
        self::assertTrue($first->isAnonymousFile($this->anonymousFile));
        self::assertNotSame(
            $first->cacheDirectory($this->vendorFile),
            $first->cacheDirectory($this->anonymousFile),
        );

        $vendorBlob = $this->writeBlob($first->cacheDirectory($this->vendorFile), $this->vendorFile, 'vendor');
        $anonymousBlob = $this->writeBlob(
            $first->cacheDirectory($this->anonymousFile),
            $this->anonymousFile,
            'anonymous',
        );
        $ordinaryBlob = $this->writeBlob(
            $first->cacheDirectory($this->ordinaryFile),
            $this->ordinaryFile,
            'ordinary',
        );

        $second = $generator->prepare($files, [$this->anonymousFile => '@typephp:anon:test']);

        self::assertSame([
            $this->vendorFile => $vendorBlob,
            $this->anonymousFile => $anonymousBlob,
        ], $second->cachedBlobs());
        self::assertSame([$this->ordinaryFile], $second->pendingFiles());
        self::assertSame(1, $second->vendorHits());
        self::assertSame(1, $second->anonymousHits());
        self::assertFileDoesNotExist($ordinaryBlob);
        self::assertFileExists($this->buildDirectory . '/cache/opcache/compile-embedded-opcodes.php');
    }

    public function testForceClearsPersistentOpcodeCaches(): void
    {
        $files = [$this->vendorFile, $this->anonymousFile];
        $anonymousKeys = [$this->anonymousFile => '@typephp:anon:test'];
        $first = $this->generator()->prepare($files, $anonymousKeys);
        $this->writeBlob($first->cacheDirectory($this->vendorFile), $this->vendorFile, 'vendor');
        $this->writeBlob($first->cacheDirectory($this->anonymousFile), $this->anonymousFile, 'anonymous');

        $forced = $this->generator(true)->prepare($files, $anonymousKeys);

        self::assertSame([], $forced->cachedBlobs());
        self::assertSame($files, $forced->pendingFiles());
    }

    public function testVendorSkipMarkerAvoidsRepeatedCompilation(): void
    {
        $generator = $this->generator();
        $first = $generator->prepare([$this->vendorFile], []);
        $cacheDirectory = $first->cacheDirectory($this->vendorFile);
        mkdir($cacheDirectory, 0777, true);
        file_put_contents(
            $cacheDirectory . '/skipped-' . hash('sha256', $this->vendorFile) . '.txt',
            $this->vendorFile,
        );

        $second = $generator->prepare([$this->vendorFile], []);

        self::assertSame([], $second->pendingFiles());
        self::assertSame(1, $second->skippedVendorCount());
        self::assertSame(0, $second->vendorHits());
    }

    private function generator(bool $force = false): EmbeddedOpcodeGenerator
    {
        return new EmbeddedOpcodeGenerator(
            $this->buildDirectory,
            'app',
            PHP_BINARY,
            [],
            PHP_VERSION,
            'build-signature',
            $force,
        );
    }

    private function writeBlob(string $cacheDirectory, string $source, string $contents): string
    {
        $blob = $cacheDirectory . '/system' . $source . '.bin';
        mkdir(dirname($blob), 0777, true);
        file_put_contents($blob, $contents);
        return $blob;
    }
}
