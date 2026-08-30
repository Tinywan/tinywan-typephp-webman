<?php

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;
use TypePhp\CompilerTest;

class CompilerBaseAdapterTest extends TestCase
{
    private string $testDir;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = sys_get_temp_dir() . '/compiler_test_' . uniqid();
        mkdir($this->testDir, 0777, true);
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->testDir)) {
            // 递归删除测试目录
            $this->removeDirectory($this->testDir);
        }
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
    
    /**
     * 测试 CompilerBase 初始化新架构
     */
    public function testCompilerBaseInitializesNewArchitecture(): void
    {
        $compiler = CompilerTest::create($this->testDir);
        
        // 使用反射检查新架构是否已初始化
        $reflection = new \ReflectionClass($compiler);
        
        $platformProp = $reflection->getProperty('platform');
        $platformProp->setAccessible(true);
        $platform = $platformProp->getValue($compiler);
        
        $backendProp = $reflection->getProperty('compilerBackend');
        $backendProp->setAccessible(true);
        $backend = $backendProp->getValue($compiler);
        
        // 新架构应该被初始化（除非检测失败）
        $this->assertNotNull($platform, 'Platform should be initialized');
        $this->assertNotNull($backend, 'Backend should be initialized');
    }
    
    /**
     * 测试平台检测方法一致性
     */
    public function testPlatformDetectionConsistency(): void
    {
        $compiler = CompilerTest::create($this->testDir);
        
        $reflection = new \ReflectionClass($compiler);
        
        // 获取旧的 isWindows 方法
        $isWindowsMethod = $reflection->getMethod('isWindows');
        $isWindowsMethod->setAccessible(true);
        $isWindowsOld = $isWindowsMethod->invoke($compiler);
        
        // 获取新的 platform 属性
        $platformProp = $reflection->getProperty('platform');
        $platformProp->setAccessible(true);
        $platform = $platformProp->getValue($compiler);
        
        if ($platform !== null) {
            $isWindowsNew = $platform instanceof \TypePhp\Platform\Windows;
            
            // 新旧方法应该一致
            $this->assertEquals($isWindowsOld, $isWindowsNew, 
                'Old and new platform detection should be consistent');
        }
    }
    
}
