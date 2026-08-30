<?php

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;
use TypePhp\CompilerTest;

class ForeachIndentTest extends TestCase
{
    public function testObjectForeachRestoresCompilerIndentation(): void
    {
        global $translator;
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $file = TYPEPHP_ROOT_PATH . '/phpunit/code/object-foreach-indent.php';
        $compiler->addFiles([$file]);
        $compiler->prepareFile($file);
        $compiler->convertFile($file);

        $reflection = new \ReflectionClass($compiler);
        $this->assertSame(0, $reflection->getProperty('indentLevel')->getValue($compiler));
    }
}
