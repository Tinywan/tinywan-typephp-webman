<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Tests\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\Build\EmbeddedArchive;
use TypePhp\Build\EmbeddedArchiveBuilder;
use TypePhp\Build\EmbeddedTableRenderer;

/**
 * @internal
 * @coversNothing
 */
final class EmbeddedArchiveBuilderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/typephp-embedded-archive-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);
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

    public function testFilesAndOpcodesShareOneDeterministicArchive(): void
    {
        $first = $this->write('first.txt', 'alpha');
        $second = $this->write('second.bin', "\0beta");
        $source = $this->write('source.php', '<?php');
        $blob = $this->write('source.bin', 'opcode');
        $archivePath = $this->directory . '/cache/archive.bin';

        $archive = (new EmbeddedArchiveBuilder())->build(
            $archivePath,
            [$first, $second],
            [$source => $blob],
            [$source => '@typephp:anon:test'],
        );

        self::assertSame("alpha\0betaopcode", file_get_contents($archivePath));
        self::assertSame([$first => [0, 5], $second => [5, 5]], $archive->fileIndex);
        self::assertSame(['@typephp:anon:test' => [10, 6]], $archive->opcodeIndex);
        self::assertSame(hash_file('sha256', $archivePath), $archive->hash);

        file_put_contents($blob, 'new-opcode');
        $updated = (new EmbeddedArchiveBuilder())->build(
            $archivePath,
            [$first, $second],
            [$source => $blob],
            [$source => '@typephp:anon:test'],
        );
        self::assertSame("alpha\0betanew-opcode", file_get_contents($archivePath));
        self::assertSame(['@typephp:anon:test' => [10, 10]], $updated->opcodeIndex);
    }

    public function testTableRendererKeepsPlatformSpecificArchiveAccessSymmetric(): void
    {
        $archive = new EmbeddedArchive(
            '/tmp/archive.bin',
            'hash',
            ['/app/config.json' => [0, 4]],
            ['@typephp:anon:test' => [4, 8]],
        );
        $renderer = new EmbeddedTableRenderer();

        $windows = $renderer->render($archive, '8.4.14', true);
        self::assertStringContainsString(
            'typephp_embedded_archive_start = typephp_embedded_archive_data()',
            $windows,
        );
        self::assertStringContainsString('{"@typephp:anon:test", typephp_embedded_archive_start + 4, 8}', $windows);
        self::assertStringContainsString('*count = 1; return typephp_opcodes;', $windows);

        $unix = $renderer->render($archive, '8.4.14', false);
        self::assertStringContainsString(
            'extern "C" const uint8_t typephp_embedded_archive_start[];',
            $unix,
        );
        self::assertStringNotContainsString('typephp_opcode_table_install(void) {}', $unix);

        $sapi = $renderer->render($archive, '8.4.14', false, false, '/app/index.php');
        self::assertStringContainsString(
            'typephp_project_entry_file(void) { return "/app/index.php"; }',
            $sapi,
        );
    }

    private function write(string $name, string $contents): string
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, $contents);
        return $path;
    }
}
