<?php

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;
use TypePhp\Build\FileScanner;

class FileScannerTest extends TestCase
{
    // ========================================================================
    // Extension type detection
    // ========================================================================

    public function testIsPhpFile(): void
    {
        $this->assertTrue(FileScanner::isPhpFile('/path/to/file.php'));
        $this->assertFalse(FileScanner::isPhpFile('/path/to/file.cc'));
        $this->assertFalse(FileScanner::isPhpFile('/path/to/file.c'));
        $this->assertFalse(FileScanner::isPhpFile('/path/to/file.py'));
    }

    public function testIsCppFile(): void
    {
        $this->assertTrue(FileScanner::isCppFile('/path/to/file.cc'));
        $this->assertTrue(FileScanner::isCppFile('/path/to/file.cpp'));
        $this->assertTrue(FileScanner::isCppFile('/path/to/file.cxx'));
        $this->assertFalse(FileScanner::isCppFile('/path/to/file.c'));
        $this->assertFalse(FileScanner::isCppFile('/path/to/file.php'));
    }

    public function testIsNativeSourceFile(): void
    {
        // C++ files
        $this->assertTrue(FileScanner::isNativeSourceFile('/path/to/file.cc'));
        $this->assertTrue(FileScanner::isNativeSourceFile('/path/to/file.cpp'));
        $this->assertTrue(FileScanner::isNativeSourceFile('/path/to/file.cxx'));
        // C files
        $this->assertTrue(FileScanner::isNativeSourceFile('/path/to/file.c'));
        // Assembly files
        $this->assertTrue(FileScanner::isNativeSourceFile('/path/to/file.s'));
        $this->assertTrue(FileScanner::isNativeSourceFile('/path/to/file.S'));
        // Objective-C files
        $this->assertTrue(FileScanner::isNativeSourceFile('/path/to/file.m'));
        $this->assertTrue(FileScanner::isNativeSourceFile('/path/to/file.mm'));
        // Not native source
        $this->assertFalse(FileScanner::isNativeSourceFile('/path/to/file.php'));
        $this->assertFalse(FileScanner::isNativeSourceFile('/path/to/file.py'));
        $this->assertFalse(FileScanner::isNativeSourceFile('/path/to/file.h'));
        $this->assertFalse(FileScanner::isNativeSourceFile('/path/to/file.hpp'));
    }

    // ========================================================================
    // getFileName / getFileExt
    // ========================================================================

    public function testGetFileName(): void
    {
        $this->assertEquals('hello', FileScanner::getFileName('/path/to/hello.php'));
        $this->assertEquals('helper', FileScanner::getFileName('/path/to/helper.c'));
        $this->assertEquals('main', FileScanner::getFileName('/path/to/main.cc'));
    }

    public function testGetFileNameWithMultipleDots(): void
    {
        $this->assertEquals('my.test', FileScanner::getFileName('/path/to/my.test.php'));
    }

    public function testGetFileExt(): void
    {
        $this->assertEquals('php', FileScanner::getFileExt('/path/to/file.php'));
        $this->assertEquals('c', FileScanner::getFileExt('/path/to/file.c'));
        $this->assertEquals('cc', FileScanner::getFileExt('/path/to/file.cc'));
        $this->assertEquals('S', FileScanner::getFileExt('/path/to/file.S'));
    }

    // ========================================================================
    // Constants
    // ========================================================================

    public function testPhpExtConstant(): void
    {
        $this->assertEquals(['php'], FileScanner::PHP_EXT);
    }

    public function testCppExtConstant(): void
    {
        $this->assertContains('cc', FileScanner::CPP_EXT);
        $this->assertContains('cpp', FileScanner::CPP_EXT);
        $this->assertContains('cxx', FileScanner::CPP_EXT);
    }

    public function testCextConstant(): void
    {
        $this->assertEquals(['c'], FileScanner::C_EXT);
    }

    public function testAsmExtConstant(): void
    {
        $this->assertContains('s', FileScanner::ASM_EXT);
        $this->assertContains('S', FileScanner::ASM_EXT);
    }

    public function testObjcExtConstant(): void
    {
        $this->assertEquals(['m'], FileScanner::OBJC_EXT);
    }

    public function testObjcxxExtConstant(): void
    {
        $this->assertEquals(['mm'], FileScanner::OBJCXX_EXT);
    }

    public function testNativeSrcExtConstant(): void
    {
        $expected = ['cpp', 'cxx', 'cc', 'c', 's', 'S', 'm', 'mm'];
        sort($expected);
        $actual = FileScanner::NATIVE_SRC_EXT;
        sort($actual);
        $this->assertEquals($expected, $actual);
    }

    // ========================================================================
    // Scan method
    // ========================================================================

    public function testScanDirectory(): void
    {
        $tmpDir = sys_get_temp_dir() . '/file_scanner_test_' . uniqid();
        mkdir($tmpDir, 0777, true);
        mkdir($tmpDir . '/sub', 0777, true);

        touch($tmpDir . '/main.php');
        touch($tmpDir . '/helper.c');
        touch($tmpDir . '/math.S');
        touch($tmpDir . '/module.cc');
        touch($tmpDir . '/lib.m');
        touch($tmpDir . '/ignored.py');
        touch($tmpDir . '/README.md');
        touch($tmpDir . '/sub/nested.cpp');

        $scanner = new FileScanner($tmpDir);
        $files = $scanner->scan();

        $this->assertSame([
            $tmpDir . '/helper.c',
            $tmpDir . '/lib.m',
            $tmpDir . '/main.php',
            $tmpDir . '/math.S',
            $tmpDir . '/module.cc',
            $tmpDir . '/sub/nested.cpp',
        ], $files);

        // Python and Markdown should not be included
        $this->assertContains($tmpDir . '/main.php', $files);
        $this->assertContains($tmpDir . '/helper.c', $files);
        $this->assertContains($tmpDir . '/math.S', $files);
        $this->assertContains($tmpDir . '/module.cc', $files);
        $this->assertContains($tmpDir . '/lib.m', $files);
        $this->assertContains($tmpDir . '/sub/nested.cpp', $files);
        $this->assertNotContains($tmpDir . '/ignored.py', $files);
        $this->assertNotContains($tmpDir . '/README.md', $files);

        // Clean up
        unlink($tmpDir . '/main.php');
        unlink($tmpDir . '/helper.c');
        unlink($tmpDir . '/math.S');
        unlink($tmpDir . '/module.cc');
        unlink($tmpDir . '/lib.m');
        unlink($tmpDir . '/ignored.py');
        unlink($tmpDir . '/README.md');
        unlink($tmpDir . '/sub/nested.cpp');
        rmdir($tmpDir . '/sub');
        rmdir($tmpDir);
    }

    public function testConstructorRejectsNonexistentDir(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FileScanner('/nonexistent/path');
    }

    public function testGetDirectory(): void
    {
        $tmpDir = sys_get_temp_dir() . '/file_scanner_getdir_' . uniqid();
        mkdir($tmpDir, 0777, true);

        $scanner = new FileScanner($tmpDir);
        $this->assertEquals($tmpDir, $scanner->getDirectory());

        rmdir($tmpDir);
    }
}
