<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class BinaryEntrypointTest extends TestCase
{
    public function testBootstrapConstantsUseTypePhpPrefix(): void
    {
        self::assertFalse(defined('ROOT_PATH'));
        self::assertFalse(defined('DEBUG'));
        self::assertSame(realpath(__DIR__ . '/..'), TYPEPHP_ROOT_PATH);
        self::assertTrue(TYPEPHP_DEBUG);
    }

    public function testStubGeneratorDoesNotPolluteTheGlobalSymbolTable(): void
    {
        self::assertTrue(function_exists('TypePhp\\StubGenerator\\generateStubFile'));
        self::assertTrue(class_exists('TypePhp\\StubGenerator\\ClassInfo'));
        self::assertFalse(function_exists('generateStubFile'));
        self::assertFalse(class_exists('ClassInfo'));
    }

    public function testSourceCheckoutEntrypointUsesPackageAutoloader(): void
    {
        $temporaryRoot = sys_get_temp_dir() . '/typephp-source-bin-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($temporaryRoot, 0777, true));

        try {
            $result = $this->runCompiler(TYPEPHP_ROOT_PATH . '/bin/tpc.php', $temporaryRoot);

            self::assertSame(0, $result['status'], $result['stderr']);
            self::assertStringContainsString('TypePHP Compiler (AOT)', $result['stdout']);
        } finally {
            $this->removeDirectory($temporaryRoot);
        }
    }

    public function testComposerVendorEntrypointUsesProjectAutoloader(): void
    {
        $temporaryRoot = sys_get_temp_dir() . '/typephp-composer-bin-' . bin2hex(random_bytes(6));
        $packageRoot = $temporaryRoot . '/vendor/swoole/typephp';

        try {
            $proxy = $this->createComposerEntrypoint($temporaryRoot, $packageRoot);
            self::assertDirectoryDoesNotExist($packageRoot . '/vendor');

            $result = $this->runCompiler($proxy, $temporaryRoot);

            self::assertSame(0, $result['status'], $result['stderr']);
            self::assertStringContainsString('TypePHP Compiler (AOT)', $result['stdout']);
        } finally {
            $this->removeDirectory($temporaryRoot);
        }
    }

    public function testComposerVendorEntrypointDryCompilesProject(): void
    {
        if (!$this->hasPhpxLibrary()) {
            self::markTestSkipped('A built PHPX library is required for the Zend PHP compiler integration test');
        }

        $temporaryRoot = sys_get_temp_dir() . '/typephp-composer-project-' . bin2hex(random_bytes(6));
        $packageRoot = $temporaryRoot . '/vendor/swoole/typephp';

        try {
            $proxy = $this->createComposerEntrypoint($temporaryRoot, $packageRoot);
            self::assertNotFalse(file_put_contents(
                $temporaryRoot . '/main.php',
                "<?php\nfunction main(): void {}\n",
            ));
            self::assertNotFalse(file_put_contents(
                $temporaryRoot . '/project.yml',
                "name: composer-entrypoint-test\nbuild-dir: build\nsources:\n  - main.php\n",
            ));

            $result = $this->runCompiler(
                $proxy,
                $temporaryRoot,
                ['project.yml', '--dry', '--no-color'],
            );

            self::assertSame(0, $result['status'], $result['stderr']);
            self::assertStringContainsString('Dry run completed:', $result['stdout']);
        } finally {
            $this->removeDirectory($temporaryRoot);
        }
    }

    /** @return array{status: int, stdout: string, stderr: string} */
    private function runCompiler(
        string $entrypoint,
        string $workingDirectory,
        array $arguments = ['--version', '--no-color'],
    ): array {
        $process = proc_open([PHP_BINARY, $entrypoint, ...$arguments], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $workingDirectory, null, ['suppress_errors' => true]);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return [
            'status' => proc_close($process),
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
        ];
    }

    private function createComposerEntrypoint(string $temporaryRoot, string $packageRoot): string
    {
        $this->copyPackageEntrypoint($packageRoot);
        $proxy = $temporaryRoot . '/vendor/bin/tpc.php';
        $proxySource = sprintf(
            "<?php\n\$GLOBALS['_composer_autoload_path'] = %s;\nrequire %s;\n",
            var_export(TYPEPHP_ROOT_PATH . '/vendor/autoload.php', true),
            var_export($packageRoot . '/bin/tpc.php', true),
        );
        self::assertNotFalse(file_put_contents($proxy, $proxySource));
        return $proxy;
    }

    private function hasPhpxLibrary(): bool
    {
        $phpxHome = getenv('PHPX_HOME');
        $roots = [TYPEPHP_ROOT_PATH . '/vendor/swoole/phpx'];
        if (is_string($phpxHome) && $phpxHome !== '') {
            array_unshift($roots, $phpxHome);
        }
        foreach ($roots as $root) {
            foreach (['lib/libphpx.so', 'lib/libphpx.a', 'lib/phpx.lib', 'bin/phpx.dll'] as $library) {
                if (is_file($root . '/' . $library)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function copyPackageEntrypoint(string $packageRoot): void
    {
        $files = [
            'bin/bootstrap.php',
            'bin/tpc.php',
            'src/compiler.php',
            'src/gen_stub.php',
            'src/polyfills.php',
        ];
        foreach ($files as $file) {
            $destination = $packageRoot . '/' . $file;
            $directory = dirname($destination);
            if (!is_dir($directory)) {
                self::assertTrue(mkdir($directory, 0777, true));
            }
            self::assertTrue(copy(TYPEPHP_ROOT_PATH . '/' . $file, $destination));
        }
        self::assertTrue(mkdir(dirname($packageRoot, 2) . '/bin', 0777, true));
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
