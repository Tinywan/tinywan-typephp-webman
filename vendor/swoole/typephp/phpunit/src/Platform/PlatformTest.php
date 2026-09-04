<?php

namespace TypePhp\Tests\Platform;

use PHPUnit\Framework\TestCase;
use TypePhp\Platform\Windows;
use TypePhp\Platform\Linux;
use TypePhp\Platform\Macos;
use TypePhp\Platform\Wasi;

class PlatformTest extends TestCase
{
    public function testWasiTargetProperties(): void
    {
        $platform = new Wasi('wasm32-unknown-wasip2');

        $this->assertSame('WASI SDK (wasm32-unknown-wasip2)', $platform->getName());
        $this->assertSame('.o', $platform->getObjectExtension());
        $this->assertSame('.wasm', $platform->getExecutableExtension());
        $this->assertSame('.a', $platform->getSharedLibraryExtension());
        $this->assertSame('LL', $platform->getIntegerLiteralSuffix());
        $this->assertSame([], $platform->getBuildLibraryWarnings('', '', 'bin'));
    }

    /**
     * 测试 Windows 平台基本功能
     */
    public function testWindowsBasic(): void
    {
        $platform = new Windows();
        
        $this->assertEquals('Windows', $platform->getName());
        $this->assertEquals('.obj', $platform->getObjectExtension());
        $this->assertEquals('.exe', $platform->getExecutableExtension());
        $this->assertEquals('.dll', $platform->getSharedLibraryExtension());
        $this->assertEquals('\\', $platform->getPathSeparator());
    }

    /**
     * 测试 Windows 包含路径格式化
     */
    public function testWindowsIncludeFlags(): void
    {
        $platform = new Windows();
        
        $paths = ['C:\PHP\include', 'C:\PHP\SDK\include'];
        $flags = $platform->getIncludeFlags($paths);
        
        $this->assertStringContainsString('/I "C:\PHP\include"', $flags);
        $this->assertStringContainsString('/I "C:\PHP\SDK\include"', $flags);
    }

    /**
     * 测试 Windows 库路径格式化
     */
    public function testWindowsLibraryPathFlags(): void
    {
        $platform = new Windows();
        
        $paths = ['C:\PHP\lib', 'C:\PHP\SDK\lib'];
        $flags = $platform->getLibraryPathFlags($paths);
        
        $this->assertStringContainsString('/LIBPATH:"C:\PHP\lib"', $flags);
        $this->assertStringContainsString('/LIBPATH:"C:\PHP\SDK\lib"', $flags);
    }

    /**
     * 测试 Windows 库文件格式化
     */
    public function testWindowsLibraryFlags(): void
    {
        $platform = new Windows();
        
        $libs = ['php8embed.lib', 'php8ts.lib'];
        $flags = $platform->getLibraryFlags($libs);
        
        $this->assertStringContainsString('"php8embed.lib"', $flags);
        $this->assertStringContainsString('"php8ts.lib"', $flags);
    }

    public function testWindowsMissingPhpxLibrariesAreFatalDiagnostics(): void
    {
        $root = sys_get_temp_dir() . '\\typephp-missing-phpx-' . bin2hex(random_bytes(6));
        mkdir($root);

        try {
            $diagnostics = (new Windows())->getBuildLibraryWarnings($root, $root, 'bin');
            $errors = array_column($diagnostics, 'error');

            $this->assertContains(
                'The PHPX import library was not found at: ' . $root . '\lib\phpx.lib',
                $errors,
            );
            $runtimeError = 'The PHPX runtime library was not found at: ' . $root . '\build\phpx.dll';
            $this->assertContains($runtimeError, $errors);
            foreach ($diagnostics as $diagnostic) {
                if (isset($diagnostic['error'])) {
                    $this->assertStringContainsString('Build PHPX first', $diagnostic['info']);
                }
            }

            $nativeExecutableDiagnostics = (new Windows())->getBuildLibraryWarnings(
                $root,
                $root,
                'bin',
                checkPhpxRuntime: false,
            );
            $this->assertNotContains(
                $runtimeError,
                array_column($nativeExecutableDiagnostics, 'error'),
            );
        } finally {
            rmdir($root);
        }
    }

    /**
     * 测试 Windows 路径规范化
     */
    public function testWindowsNormalizePath(): void
    {
        $platform = new Windows();
        
        $this->assertEquals('src\Backend', $platform->normalizePath('src/Backend'));
        $this->assertEquals('C:\PHP\include', $platform->normalizePath('C:/PHP/include'));
    }

    /**
     * 测试 Windows 路径组合
     */
    public function testWindowsJoinPath(): void
    {
        $platform = new Windows();
        
        $path = $platform->joinPath('src', 'Php', 'Backend');
        $this->assertEquals('src\Php\Backend', $path);
    }

    public function testTargetExtensions(): void
    {
        $windows = new Windows();
        $linux = new Linux();
        $macos = new Macos();

        $this->assertSame('.exe', $windows->getTargetExtension('bin'));
        $this->assertSame('.dll', $windows->getTargetExtension('ext'));
        $this->assertSame('.dll', $windows->getTargetExtension('lib'));
        $this->assertSame('', $linux->getTargetExtension('bin'));
        $this->assertSame('.so', $linux->getTargetExtension('ext'));
        $this->assertSame('.so', $linux->getTargetExtension('lib'));
        $this->assertSame('', $macos->getTargetExtension('bin'));
        $this->assertSame('.so', $macos->getTargetExtension('ext'));
        $this->assertSame('.dylib', $macos->getTargetExtension('lib'));
    }

    public function testPlatformPathPrefixRemoval(): void
    {
        $windows = new Windows();
        $linux = new Linux();

        $this->assertSame('src\app.php', $windows->removeCommonPrefix('C:\project', 'C:/project/src/app.php'));
        $this->assertSame('src/app.php', $linux->removeCommonPrefix('/project', '/project/src/app.php'));
    }

    public function testPlatformPathPrefixRemovalUsesPathSegments(): void
    {
        $windows = new Windows();
        $linux = new Linux();

        $this->assertSame('project2\src\app.php', $windows->removeCommonPrefix('C:\project', 'C:/project2/src/app.php'));
        $this->assertSame('project2/src/app.php', $linux->removeCommonPrefix('/tmp/project', '/tmp/project2/src/app.php'));
    }

    /**
     * 测试 Windows 子系统选项
     */
    public function testWindowsSubsystemOptions(): void
    {
        $platform = new Windows();
        
        // 无控制台
        $options = $platform->getSubsystemOptions(true);
        $this->assertStringContainsString('/SUBSYSTEM:WINDOWS', $options);
        $this->assertStringContainsString('/ENTRY:mainCRTStartup', $options);
        
        // 有控制台
        $options = $platform->getSubsystemOptions(false);
        $this->assertEquals('', $options);
    }

    /**
     * 测试 Windows CRT 配置
     */
    public function testWindowsCrtConfig(): void
    {
        $platform = new Windows();
        
        $config = $platform->getCrtConfig();
        $this->assertEquals('/NODEFAULTLIB:LIBCMT', $config);
        $this->assertSame('/DLL', $platform->getSharedLinkFlag());
    }

    /**
     * 测试 Windows 调试选项
     */
    public function testWindowsDebugOptions(): void
    {
        $platform = new Windows();
        
        // 启用调试
        $options = $platform->getDebugOptions(true);
        $this->assertEquals('/DEBUG', $options);
        
        // 禁用调试
        $options = $platform->getDebugOptions(false);
        $this->assertEquals('', $options);
    }

    /**
     * 测试 Linux 平台基本功能
     */
    public function testLinuxBasic(): void
    {
        $platform = new Linux();
        
        $this->assertEquals('Linux', $platform->getName());
        $this->assertEquals('.o', $platform->getObjectExtension());
        $this->assertEquals('', $platform->getExecutableExtension());
        $this->assertEquals('.so', $platform->getSharedLibraryExtension());
        $this->assertEquals('/', $platform->getPathSeparator());
    }

    /**
     * 测试 Linux 包含路径格式化
     */
    public function testLinuxIncludeFlags(): void
    {
        $platform = new Linux();
        
        $paths = ['/usr/include/php', '/usr/local/include'];
        $flags = $platform->getIncludeFlags($paths);
        
        $this->assertStringContainsString('-I' . escapeshellarg('/usr/include/php'), $flags);
        $this->assertStringContainsString('-I' . escapeshellarg('/usr/local/include'), $flags);
    }

    /**
     * 测试 Linux 库路径格式化
     */
    public function testLinuxLibraryPathFlags(): void
    {
        $platform = new Linux();
        
        $paths = ['/usr/lib', '/usr/local/lib'];
        $flags = $platform->getLibraryPathFlags($paths);
        
        $this->assertStringContainsString('-L' . escapeshellarg('/usr/lib'), $flags);
        $this->assertStringContainsString('-L' . escapeshellarg('/usr/local/lib'), $flags);
    }

    /**
     * 测试 Linux 库文件格式化
     */
    public function testLinuxLibraryFlags(): void
    {
        $platform = new Linux();
        
        $libs = ['/usr/lib/libphp.so', '/usr/lib/libphpx.a'];
        $flags = $platform->getLibraryFlags($libs);
        
        $this->assertStringContainsString('-lphp', $flags);
        $this->assertStringContainsString('-lphpx', $flags);
    }

    /**
     * 测试 Linux RPATH 选项
     */
    public function testLinuxRpathOptions(): void
    {
        $platform = new Linux();
        
        $paths = ['/usr/lib', '/usr/local/lib'];
        $options = $platform->getRpathOptions($paths);
        
        $this->assertStringContainsString('-Wl,-rpath,' . escapeshellarg('/usr/lib'), $options);
        $this->assertStringContainsString('-Wl,-rpath,' . escapeshellarg('/usr/local/lib'), $options);
    }

    /**
     * 测试 Linux PIC 标志
     */
    public function testLinuxPicFlag(): void
    {
        $platform = new Linux();
        
        $this->assertEquals('-fPIC', $platform->getPicFlag());
    }

    public function testIntegerLiteralSuffixes(): void
    {
        $this->assertSame('L', (new Linux())->getIntegerLiteralSuffix());
        $this->assertSame('LL', (new Macos())->getIntegerLiteralSuffix());
        $this->assertSame('LL', (new Windows())->getIntegerLiteralSuffix());
    }

    /**
     * 测试 Linux 共享库链接标志
     */
    public function testLinuxSharedLinkFlag(): void
    {
        $platform = new Linux();
        
        $this->assertEquals('-shared', $platform->getSharedLinkFlag());
        $this->assertSame('', $platform->getSubsystemOptions(true));
        $this->assertSame('', $platform->getCrtConfig());
    }

    public function testLinuxPhpHomeSelectsMatchingVersionedPhpConfig(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Unix php-config lookup test');
        }

        $root = sys_get_temp_dir() . '/typephp-php-home-' . bin2hex(random_bytes(6));
        $phpHome = $root . '/php';
        $rightInclude = $phpHome . '/include/right';
        $wrongInclude = $phpHome . '/include/wrong';
        mkdir($phpHome . '/bin', 0755, true);
        mkdir($rightInclude, 0755, true);
        mkdir($wrongInclude, 0755, true);

        $versionedConfig = $phpHome . '/bin/php-config' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        $wrongMinor = PHP_MINOR_VERSION === 4 ? 5 : 4;
        $this->writePhpConfig($versionedConfig, PHP_VERSION, $phpHome, $rightInclude);
        $this->writePhpConfig(
            $phpHome . '/bin/php-config',
            PHP_MAJOR_VERSION . '.' . $wrongMinor . '.0',
            $phpHome,
            $wrongInclude,
        );

        $previousPhpHome = getenv('PHP_HOME');
        try {
            putenv('PHP_HOME=' . $phpHome);
            $platform = new Linux();

            $this->assertSame($phpHome, $platform->getPhpDir());
            $this->assertSame([$rightInclude], $platform->buildPhpIncludePaths($phpHome));
        } finally {
            $previousPhpHome === false
                ? putenv('PHP_HOME')
                : putenv('PHP_HOME=' . $previousPhpHome);
            unlink($versionedConfig);
            unlink($phpHome . '/bin/php-config');
            rmdir($rightInclude);
            rmdir($wrongInclude);
            rmdir($phpHome . '/include');
            rmdir($phpHome . '/bin');
            rmdir($phpHome);
            rmdir($root);
        }
    }

    public function testLinuxPhpHomeDoesNotFallBackToPathPhpConfig(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Unix php-config lookup test');
        }

        $root = sys_get_temp_dir() . '/typephp-php-home-missing-' . bin2hex(random_bytes(6));
        $phpHome = $root . '/php';
        mkdir($phpHome . '/bin', 0755, true);
        $previousPhpHome = getenv('PHP_HOME');

        try {
            putenv('PHP_HOME=' . $phpHome);
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('PHP_HOME does not provide an executable bin/php-config');
            (new Linux())->buildPhpIncludePaths($phpHome);
        } finally {
            $previousPhpHome === false
                ? putenv('PHP_HOME')
                : putenv('PHP_HOME=' . $previousPhpHome);
            rmdir($phpHome . '/bin');
            rmdir($phpHome);
            rmdir($root);
        }
    }

    private function writePhpConfig(string $path, string $version, string $prefix, string $include): void
    {
        $script = sprintf(
            "#!/bin/sh\ncase \"\$1\" in\n  --version) printf '%%s\\n' %s ;;\n  --prefix) printf '%%s\\n' %s ;;\n  --includes) printf '%%s\\n' %s ;;\nesac\n",
            escapeshellarg($version),
            escapeshellarg($prefix),
            escapeshellarg('-I' . $include),
        );
        file_put_contents($path, $script);
        chmod($path, 0755);
    }

    /**
     * 测试 macOS 平台基本功能
     */
    public function testMacosBasic(): void
    {
        $platform = new Macos();
        
        $this->assertEquals('macOS', $platform->getName());
        $this->assertEquals('.o', $platform->getObjectExtension());
        $this->assertEquals('', $platform->getExecutableExtension());
        $this->assertEquals('.dylib', $platform->getSharedLibraryExtension());
        $this->assertEquals('/', $platform->getPathSeparator());
    }

    /**
     * 测试 macOS install_name 选项
     */
    public function testMacosInstallName(): void
    {
        $platform = new Macos();
        
        $option = $platform->getCurrentInstallNameOption('/usr/lib/libtest.dylib');
        $this->assertStringContainsString('-install_name', $option);
        $this->assertStringContainsString('/usr/lib/libtest.dylib', $option);
    }

    /**
     * 测试 macOS 共享库链接标志
     */
    public function testMacosSharedLinkFlag(): void
    {
        $platform = new Macos();
        
        $this->assertEquals('-dynamiclib', $platform->getSharedLinkFlag());
    }

    public function testMacosHomebrewSearchPaths(): void
    {
        $platform = new Macos();

        $includePaths = $platform->buildPhpIncludePaths('/path/that/does/not/exist');
        $this->assertContains('/opt/homebrew/include', $includePaths);
        $this->assertContains('/usr/local/include', $includePaths);

        $libraryPaths = $platform->buildPhpLibPaths('/path/that/does/not/exist');
        $this->assertContains('/opt/homebrew/lib', $libraryPaths);
        $this->assertContains('/usr/local/lib', $libraryPaths);

        $this->assertStringContainsString(
            '-I' . escapeshellarg('/opt/homebrew/include'),
            $platform->getIncludeFlags($includePaths),
        );
        $this->assertStringContainsString(
            '-L' . escapeshellarg('/opt/homebrew/lib'),
            $platform->getLibraryPathFlags($libraryPaths),
        );
    }

    /**
     * 测试空数组处理
     */
    public function testEmptyArrays(): void
    {
        $windows = new Windows();
        $linux = new Linux();
        $macos = new Macos();
        
        // 所有平台应该正确处理空数组
        $this->assertEquals('', $windows->getIncludeFlags([]));
        $this->assertEquals('', $windows->getLibraryPathFlags([]));
        $this->assertEquals('', $windows->getLibraryFlags([]));
        
        $this->assertEquals('', $linux->getIncludeFlags([]));
        $this->assertEquals('', $linux->getLibraryPathFlags([]));
        $this->assertEquals('', $linux->getLibraryFlags([]));
        
        $this->assertEquals('', $macos->getIncludeFlags([]));
        $this->assertEquals('', $macos->getLibraryPathFlags([]));
        $this->assertEquals('', $macos->getLibraryFlags([]));
    }
}
