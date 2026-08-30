<?php

use PHPUnit\Framework\TestCase;
use TypePhp\CompilerTest;

final class WrapperArgumentMoveTest extends TestCase
{
    public function testWrapperConsumesOnlyDeadByValueWrappers(): void
    {
        global $translator;
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $file = __DIR__ . '/../code/wrapper-argument-move.php';
        $compiler->addFiles([$file]);
        $compiler->prepareFile($file);
        $cppFile = $compiler->convertFile($file);
        $code = file_get_contents($cppFile);

        $this->assertStringContainsString(
            'php_phpunit_wrapper_argument_move(php::takeValue(arg_value),php::takeValue(arg_text),'
            . 'php::takeValue(arg_items),php::takeValue(arg_object),arg_count,arg_reference,'
            . 'php::takeValue(arg_rest));',
            $code,
        );
        $this->assertStringNotContainsString('php::takeValue(arg_count)', $code);
        $this->assertStringNotContainsString('php::takeValue(arg_reference)', $code);
        $this->assertStringContainsString(
            'php_phpunitwrapperargumentmovetarget__consume(this_, php::takeValue(arg_value));',
            $code,
        );
        $this->assertStringNotContainsString('php::takeValue(this_)', $code);
    }
}
