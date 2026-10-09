<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhpTest\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\Build\SapiApplicationLinker;

/**
 * @internal
 * @coversNothing
 */
final class SapiApplicationLinkerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/typephp-sapi-linker-' . uniqid('', true);
        mkdir($this->directory . '/php/sapi/cli', 0777, true);
        file_put_contents(
            $this->directory . '/php/sapi/cli/php_cli.c',
            <<<'C'
#include "php.h"
static zend_result cli_seek_file_begin(zend_file_handle *file_handle, char *script_file)
{
}
int main(int argc, char *argv[])
{
	int ini_ignore = 0;
	sapi_module_struct *sapi_module_ptr = &cli_sapi_module;
	argv = save_ps_args(argc, argv);
	cleanup_ps_args(argv);
}
C,
        );
        file_put_contents(
            $this->directory . '/php/sapi/cli/php_cli_server.c',
            <<<'C'
#include "php.h"
static void translate(void)
{
	php_sys_stat(buf, &sb);
	php_sys_stat(buf, &sb);
}
C,
        );
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

    public function testCliAdapterPrependsEmbeddedEntryToSavedArguments(): void
    {
        $entry = $this->directory . '/project/index.php';
        $linker = new SapiApplicationLinker(
            $this->directory . '/php',
            $this->directory . '/php-build',
            $this->directory . '/libphpx.a',
            [],
            $this->directory . '/project-build',
            ['cli'],
            $entry,
            static function (string $message): void {},
        );
        $method = new \ReflectionMethod($linker, 'generateSapiAdapters');
        $method->invoke($linker, 'sapi/cli/php_cli.lo', '');

        $sources = glob($this->directory . '/project-build/cache/sapi/adapters/*/php_cli.c');
        self::assertIsArray($sources);
        self::assertCount(1, $sources);
        $code = (string) file_get_contents($sources[0]);
        self::assertStringContainsString('typephp_saved_argv = argv;', $code);
        self::assertStringContainsString(
            'typephp_cli_prepare_arguments(&argc, &argv, OPTIONS, &typephp_application_argv)',
            $code,
        );
        self::assertStringContainsString('typephp_cli_release_arguments(typephp_application_argv);', $code);
        self::assertStringContainsString('cleanup_ps_args(typephp_saved_argv);', $code);
        self::assertStringNotContainsString('php_getopt(', $code);
        self::assertStringNotContainsString('typephp_entry_script', $code);

        $serverSources = glob($this->directory . '/project-build/cache/sapi/adapters/*/php_cli_server.c');
        self::assertIsArray($serverSources);
        self::assertCount(1, $serverSources);
        $serverCode = (string) file_get_contents($serverSources[0]);
        self::assertSame(2, substr_count($serverCode, 'typephp_cli_server_stat(buf, &sb)'));
        self::assertStringContainsString('typephp_embedded_path_kind(path)', $serverCode);
        self::assertStringNotContainsString('php_sys_stat(buf, &sb)', $serverCode);
    }
}
