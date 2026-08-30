<?php

namespace TypePhp\Tests\Build;

use PHPUnit\Framework\TestCase;
use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;
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
        };

        return $compiler->withPlatform($platform);
    }

    private function withPhpxHome(string $dir): callable
    {
        $previous = getenv('PHPX_HOME');
        putenv('PHPX_HOME=' . $dir);
        return static function () use ($previous): void {
            if ($previous === false) {
                putenv('PHPX_HOME');
            } else {
                putenv('PHPX_HOME=' . $previous);
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
