<?php

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;
use TypePhp\Platform\PlatformFactory;
use TypePhp\Backend\CompilerFactory;
use TypePhp\Platform\Windows;
use TypePhp\Platform\Linux;
use TypePhp\Platform\Macos;

class FactoryTest extends TestCase
{
    private string|false $originalPath;
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalPath = getenv('PATH');
        $this->tmpDir = sys_get_temp_dir() . '/compiler_factory_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->originalPath === false) {
            putenv('PATH');
        } else {
            putenv('PATH=' . $this->originalPath);
        }
        $this->removeDirectory($this->tmpDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createFakeExecutable(string $name): string
    {
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, "#!/bin/sh\nexit 0\n");
        chmod($path, 0755);
        return $path;
    }

    /**
     * 测试 PlatformFactory 自动检测
     */
    public function testPlatformFactoryAutoDetect(): void
    {
        $platform = PlatformFactory::create();
        
        $this->assertNotNull($platform);
        $this->assertTrue($platform->isCurrent());
    }

    /**
     * 测试 PlatformFactory 平台判断
     */
    public function testPlatformFactoryPlatformChecks(): void
    {
        // 至少有一个平台判断返回 true
        $isAnyPlatform = PlatformFactory::isWindows() || 
                        PlatformFactory::isLinux() || 
                        PlatformFactory::isMacos();
        
        $this->assertTrue($isAnyPlatform);
    }

    /**
     * 测试 PlatformFactory 获取平台名称
     */
    public function testPlatformFactoryGetName(): void
    {
        $name = PlatformFactory::getCurrentPlatformName();
        
        $this->assertNotEmpty($name);
        $this->assertIsString($name);
    }

    /**
     * 测试 CompilerFactory 自动创建
     */
    public function testCompilerFactoryAutoCreate(): void
    {
        $platform = PlatformFactory::create();
        $compiler = CompilerFactory::create($platform);
        
        $this->assertNotNull($compiler);
        $this->assertEquals($platform, $compiler->getPlatform());
    }

    /**
     * 测试 CompilerFactory 按名称创建 - MSVC
     */
    public function testCompilerFactoryCreateMsvc(): void
    {
        $platform = new Windows();
        $compiler = CompilerFactory::createByName('msvc', $platform);
        
        $this->assertEquals('MSVC', $compiler->getName());
    }

    /**
     * 测试 CompilerFactory 按名称创建 - GCC
     */
    public function testCompilerFactoryCreateGcc(): void
    {
        $platform = new Linux();
        $compiler = CompilerFactory::createByName('gcc', $platform);
        
        $this->assertEquals('GCC', $compiler->getName());
    }

    /**
     * 测试 CompilerFactory 按名称创建 - Clang
     */
    public function testCompilerFactoryCreateClang(): void
    {
        $platform = new Linux();
        $compiler = CompilerFactory::createByName('clang', $platform);
        
        $this->assertEquals('Clang', $compiler->getName());
    }

    /**
     * 测试 CompilerFactory 不支持的编译器
     */
    public function testCompilerFactoryUnsupportedCompiler(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported compiler');
        
        $platform = new Linux();
        CompilerFactory::createByName('unsupported', $platform);
    }

    /**
     * 测试 CompilerFactory 自动检测
     */
    public function testCompilerFactoryAutoDetect(): void
    {
        $result = CompilerFactory::autoDetect();
        
        $this->assertArrayHasKey('platform', $result);
        $this->assertArrayHasKey('compiler', $result);
        $this->assertNotNull($result['platform']);
        $this->assertNotNull($result['compiler']);
    }

    /**
     * 测试 CompilerFactory 自动检测指定编译器
     */
    public function testCompilerFactoryAutoDetectWithCompiler(): void
    {
        $result = CompilerFactory::autoDetect('gcc');
        
        $this->assertNotNull($result['platform']);
        $this->assertEquals('GCC', $result['compiler']->getName());
    }

    /**
     * 测试平台与编译器匹配 - Windows + MSVC
     */
    public function testPlatformCompilerMatchWindowsMsvc(): void
    {
        $platform = new Windows();
        $compiler = CompilerFactory::create($platform);
        
        $this->assertEquals('MSVC', $compiler->getName());
    }

    /**
     * 测试平台与编译器匹配 - Linux + GCC
     */
    public function testPlatformCompilerMatchLinuxGcc(): void
    {
        $platform = new Linux();
        $compiler = CompilerFactory::create($platform);
        
        $this->assertEquals('GCC', $compiler->getName());
    }

    /**
     * 测试平台与编译器匹配 - macOS + Clang
     */
    public function testPlatformCompilerMatchMacosClang(): void
    {
        $platform = new Macos();
        $compiler = CompilerFactory::create($platform);
        
        $this->assertEquals('Clang', $compiler->getName());
    }

    /**
     * 测试编译器可以获取平台实例
     */
    public function testCompilerGetPlatform(): void
    {
        $platform = new Windows();
        $compiler = CompilerFactory::create($platform);
        
        $retrievedPlatform = $compiler->getPlatform();
        
        $this->assertSame($platform, $retrievedPlatform);
    }

    public function testCompilerCommandProgramParsesArgumentsAndQuotes(): void
    {
        $this->assertSame('clang++', CompilerFactory::getCommandProgram('clang++ -stdlib=libc++'));
        $this->assertSame('/opt/llvm/bin/clang++', CompilerFactory::getCommandProgram('"/opt/llvm/bin/clang++" -O2'));
        $this->assertSame('C:\\LLVM\\bin\\clang++.exe', CompilerFactory::getCommandProgram('"C:\\LLVM\\bin\\clang++.exe" -O2'));
        $this->assertSame('', CompilerFactory::getCommandProgram('   '));
    }

    public function testCompilerCommandExecutableUsesPathAndIgnoresArguments(): void
    {
        $this->createFakeExecutable('fake-g++');
        putenv('PATH=' . $this->tmpDir);

        $this->assertTrue(CompilerFactory::isCommandExecutable('fake-g++ -std=c++20'));
        $this->assertFalse(CompilerFactory::isCommandExecutable('missing-g++ -std=c++20'));
    }

    public function testCompilerCommandExecutableAcceptsQuotedAbsolutePath(): void
    {
        $compiler = $this->createFakeExecutable('quoted-clang++');

        $this->assertTrue(CompilerFactory::isCommandExecutable('"' . $compiler . '" -O2'));
    }
}
