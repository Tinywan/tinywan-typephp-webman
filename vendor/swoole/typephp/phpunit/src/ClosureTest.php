<?php

use TypePhp\CompilerTest;

class ClosureTest extends \BaseTest
{
    public function testUseReferenceCaptureCompiles(): void
    {
        global $translator;

        $testFile = TYPEPHP_ROOT_PATH . '/phpunit/code/closure/use-reference-capture.php';
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $compiler->addFiles([$testFile]);
        $compiler->prepareFile($testFile);
        $compiler->convertFile($testFile);

        $this->assertTrue(true);
    }

    public function testClosureRebindingIsRejectedAtCompileTime(): void
    {
        $this->exec(
            'Closure::call() is not supported',
            'closure/closure-call-unsupported.php'
        );
        $this->exec(
            'Closure::call() is not supported',
            'closure/closure-call-typed-unsupported.php'
        );
        $this->exec(
            'Closure::bindTo() is not supported',
            'closure/closure-bind-to-unsupported.php'
        );
        $this->exec(
            'Closure::bind() is not supported',
            'closure/closure-bind-unsupported.php'
        );
    }
}
