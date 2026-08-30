<?php

namespace TypePhp\Tests\Generator;

use PHPUnit\Framework\TestCase;
use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

class FiberGeneratorTest extends TestCase
{
    public function testWasiTargetRejectsGeneratorDuringPreparation(): void
    {
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $reflection = new \ReflectionClass($compiler);
        $reflection->getProperty('targetPlatform')->setValue($compiler, 'wasm32-wasip2');
        $file = __DIR__ . '/../../code/generator-conversion-error.php';
        $compiler->addFiles([$file]);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Fiber and Generator are not supported by the WASI target');
        $compiler->prepareFile($file);
    }

    public function testCompilerStateIsRestoredAfterGeneratorConversionError(): void
    {
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $file = __DIR__ . '/../../code/generator-conversion-error.php';
        $compiler->addFiles([$file]);
        $compiler->prepareFile($file);

        try {
            $compiler->convertFile($file);
            $this->fail('The variable-variable expression should fail conversion');
        } catch (TestError $e) {
            $this->assertStringContainsString('The `$$` syntax is not supported', $e->getMessage());
        }

        $reflection = new \ReflectionClass($compiler);
        $this->assertFalse($reflection->getProperty('inGeneratorBody')->getValue($compiler));
        $this->assertSame(0, $reflection->getProperty('indentLevel')->getValue($compiler));
        $this->assertSame('', $reflection->getProperty('function')->getValue($compiler));
        $this->assertNull($reflection->getProperty('functionDef')->getValue($compiler));
        $context = $reflection->getProperty('context')->getValue($compiler);
        $this->assertFalse($context->inClosure);
    }
}
