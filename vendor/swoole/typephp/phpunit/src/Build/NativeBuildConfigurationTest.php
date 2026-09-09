<?php

namespace TypePhp\Tests\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;
use TypePhp\Platform\Ios;
use TypePhp\Platform\Android;
use TypePhp\Platform\Linux;
use TypePhp\Platform\Macos;
use TypePhp\Platform\PlatformBase;
use TypePhp\Platform\Windows;

final class NativeBuildConfigurationTest extends TestCase
{
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->temporaryDirectories as $dir) {
            $this->removeDirectory($dir);
        }
        $this->temporaryDirectories = [];
    }

    /**
     * 三个平台都必须能解析到对应名称的 phpx 库：
     * Windows -> phpx.lib，Linux -> libphpx.so，macOS -> libphpx.dylib。
     */
    public function testFindPhpxLibraryResolvesAllPlatforms(): void
    {
        $cases = [
            'Linux' => [new Linux(), '/lib/libphpx.so'],
            'macOS' => [new Macos(), '/lib/libphpx.dylib'],
            'Windows' => [new Windows(), '\\lib\\phpx.lib'],
        ];

        foreach ($cases as $label => [$platform, $relativeLib]) {
            $phpxDir = $this->temporaryDirectory('phpx-' . strtolower($label));
            // getPhpxDir() 会通过 realpath 规范化（macOS 上 /var -> /private/var），
            // 这里按同样的规范化路径创建库文件，保证断言一致。
            $phpxDir = realpath($phpxDir) ?: $phpxDir;
            if ($platform instanceof Windows) {
                mkdir($phpxDir . '\\lib', 0777, true);
                $libPath = $phpxDir . '\\lib\\phpx.lib';
                touch($libPath);
            } else {
                $libPath = $phpxDir . $relativeLib;
                mkdir(dirname($libPath), 0777, true);
                touch($libPath);
            }

            $restore = $this->withPhpxHome($phpxDir);
            try {
                $compiler = $this->newCompiler($platform);
                $this->assertSame(
                    $libPath,
                    $compiler->findPhpxLibraryForTest(),
                    "{$label} 平台 phpx 库解析失败"
                );
            } finally {
                $restore();
            }
        }
    }

    public function testValidatePhpxLibraryFailsFastWhenMissing(): void
    {
        $phpxDir = $this->temporaryDirectory('phpx-missing');
        mkdir($phpxDir . '/lib', 0777, true);

        $restore = $this->withPhpxHome($phpxDir);
        try {
            $compiler = $this->newCompiler(new Macos());
            $this->expectException(TestError::class);
            $this->expectExceptionMessage('phpx library not found');
            $compiler->validatePhpxLibraryForTest();
        } finally {
            $restore();
        }
    }

    public function testIosResolvesPhpxArchiveFromIntegratedPhpxSdk(): void
    {
        $phpxDir = $this->temporaryDirectory('phpx-ios-host');
        mkdir($phpxDir . '/lib', 0777, true);
        // A host-side archive with the same name must never win.
        touch($phpxDir . '/lib/libphpx.a');

        $iosSdk = $phpxDir . '/ios/iphoneos-arm64';
        mkdir($iosSdk . '/lib', 0777, true);
        file_put_contents($iosSdk . '/.typephp-ios-sdk-abi', "typephp-iphoneos-arm64-sdk-abi-v1\n");
        $targetArchive = $iosSdk . '/lib/libphpx.a';
        touch($targetArchive);

        $restorePhpx = $this->withEnvironment('PHPX_HOME', $phpxDir);
        $restorePhp = $this->withEnvironment('PHP_HOME', $this->temporaryDirectory('unrelated-host-php'));
        try {
            $compiler = $this->newCompiler(new Ios());
            self::assertSame($targetArchive, $compiler->findPhpxLibraryForTest());
            self::assertSame($iosSdk, $compiler->getPhpDir());
            self::assertSame([$iosSdk . '/lib'], $compiler->getLibraryPathsForTest());
            self::assertSame($iosSdk . '/include/phpx', $compiler->getIncludePathsForTest()[0]);
            self::assertNotContains($phpxDir . '/include', $compiler->getIncludePathsForTest());
            self::assertSame(
                [$targetArchive, 'php', 'gmp', 'gmpxx', 'mpfr', 'c++'],
                $compiler->getLibrariesForTest(),
            );
            self::assertContains('PHPX_IOS=1', $compiler->getCommonCompileOptionsForTest()['user_defines']);
        } finally {
            $restorePhp();
            $restorePhpx();
        }
    }

    public function testAndroidResolvesSelfContainedSdkAndSystemLibraries(): void
    {
        $phpxDir = $this->temporaryDirectory('phpx-android-host');
        $androidSdk = $this->temporaryDirectory('phpx-android-sdk');
        mkdir($androidSdk . '/lib', 0777, true);
        mkdir($androidSdk . '/include/phpx', 0777, true);
        file_put_contents(
            $androidSdk . '/.typephp-android-sdk-abi',
            "typephp-android-arm64-v8a-api24-phpx-sdk-abi-v1\n",
        );
        $phpxArchive = $androidSdk . '/lib/libphpx.a';
        $phpArchive = $androidSdk . '/lib/libphp.a';
        touch($phpxArchive);
        touch($phpArchive);

        $restorePhpx = $this->withEnvironment('PHPX_HOME', $phpxDir);
        $restoreSdk = $this->withEnvironment('PHPX_ANDROID_SDK_DIR', $androidSdk);
        try {
            $compiler = $this->newCompiler(new Android());
            self::assertSame($phpxArchive, $compiler->findPhpxLibraryForTest());
            self::assertSame($androidSdk, $compiler->getPhpDir());
            self::assertSame([$androidSdk . '/lib'], $compiler->getLibraryPathsForTest());
            self::assertSame($androidSdk . '/include/phpx', $compiler->getIncludePathsForTest()[0]);
            self::assertSame(
                [$phpxArchive, $phpArchive, 'log', 'android', 'dl', 'm'],
                $compiler->getLibrariesForTest(),
            );
            self::assertContains('PHPX_ANDROID=1', $compiler->getCommonCompileOptionsForTest()['user_defines']);
        } finally {
            $restoreSdk();
            $restorePhpx();
        }

        self::assertTrue(Android::supportsTarget('aarch64-linux-android24'));
        self::assertFalse(Android::supportsTarget('x86_64-linux-android24'));
        self::assertSame(24, Android::getApiLevel('aarch64-linux-android24'));
    }

    public function testNativeModulesDoNotFallBackToStaticPhpx(): void
    {
        $phpxDir = $this->temporaryDirectory('phpx-static-module');
        mkdir($phpxDir . '/lib', 0777, true);
        touch($phpxDir . '/lib/libphpx.a');

        $restore = $this->withPhpxHome($phpxDir);
        try {
            foreach ([CompilerTest::BUILD_MODE_EXT, CompilerTest::BUILD_MODE_LIB] as $mode) {
                $compiler = $this->newCompiler(new Linux());
                $compiler->setBuildMode($mode);
                self::assertNull($compiler->findPhpxLibraryForTest(), $mode);
            }
        } finally {
            $restore();
        }
    }

    public function testUnixExtensionDoesNotLinkEmbedPhpLibrary(): void
    {
        $phpxDir = $this->temporaryDirectory('phpx-extension-link');
        mkdir($phpxDir . '/lib', 0777, true);
        touch($phpxDir . '/lib/libphpx.so');

        $restore = $this->withPhpxHome($phpxDir);
        try {
            $compiler = $this->newCompiler(new Linux());
            $compiler->setBuildMode(CompilerTest::BUILD_MODE_EXT);
            $libraries = $compiler->getLibrariesForTest();

            self::assertNotContains('php', $libraries);
            self::assertContains($phpxDir . '/lib/libphpx.so', $libraries);
        } finally {
            $restore();
        }
    }

    public function testPhpxDirPrefersPhpxHomeOverVendor(): void
    {
        $root = $this->temporaryDirectory('phpx-priority-root');
        mkdir($root . '/vendor/swoole/phpx', 0777, true);
        $phpxHome = $this->temporaryDirectory('phpx-home');
        $phpxHome = realpath($phpxHome) ?: $phpxHome;

        $compiler = new class($root) extends CompilerTest {
            public function __construct(string $rootPath)
            {
                parent::__construct($rootPath);
                $this->forTest = true;
            }

            public function getPhpxDirForTest(): string
            {
                return $this->getPhpxDir();
            }
        };

        $restore = $this->withPhpxHome($phpxHome);
        try {
            $this->assertSame($phpxHome, $compiler->getPhpxDirForTest());
        } finally {
            $restore();
        }
    }

    private function newCompiler(PlatformBase $platform): object
    {
        $root = $this->temporaryDirectory('phpx-compiler-root');
        $compiler = new class($root) extends CompilerTest {
            public function __construct(string $rootPath)
            {
                parent::__construct($rootPath);
                $this->forTest = true;
            }

            public function withPlatform(PlatformBase $platform): self
            {
                $this->platform = $platform;
                return $this;
            }

            public function findPhpxLibraryForTest(): ?string
            {
                return $this->findPhpxLibrary();
            }

            public function validatePhpxLibraryForTest(): void
            {
                $this->validatePhpxLibrary();
            }

            public function getLibrariesForTest(): array
            {
                return $this->getLibraries();
            }

            public function getIncludePathsForTest(): array
            {
                return $this->getIncludePaths();
            }

            public function getLibraryPathsForTest(): array
            {
                return $this->getLibraryPaths();
            }

            public function getCommonCompileOptionsForTest(): array
            {
                return $this->getCommonCompileCommandOptions()->toArray();
            }
        };

        return $compiler->withPlatform($platform);
    }

    private function withPhpxHome(string $dir): callable
    {
        return $this->withEnvironment('PHPX_HOME', $dir);
    }

    private function withEnvironment(string $name, string $value): callable
    {
        $previous = getenv($name);
        putenv($name . '=' . $value);
        return static function () use ($name, $previous): void {
            if ($previous === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $previous);
            }
        };
    }

    private function temporaryDirectory(string $prefix): string
    {
        $dir = sys_get_temp_dir() . '/' . $prefix . '_' . uniqid();
        mkdir($dir, 0777, true);
        $this->temporaryDirectories[] = $dir;
        return $dir;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
