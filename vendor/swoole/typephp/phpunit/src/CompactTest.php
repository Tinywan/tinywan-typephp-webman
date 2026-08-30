<?php

use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

class CompactTest extends \BaseTest
{
    public function testCompactThisOutsideClass(): void
    {
        $this->exec(
            'Cannot use compact("this") outside of class method',
            'compact-this-outside-class.php'
        );
    }

    public function testCompactThisInsideClass(): void
    {
        $testFile = __DIR__ . '/../code/compact-this-inside-class.php';
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $compiler->addFiles([$testFile]);
        $compiler->prepareFile($testFile);
        $compiler->convertFile($testFile);

        $this->assertTrue(true); // 无异常即通过
    }

    public function testCompactThisMixedContext(): void
    {
        // compact('this') 在类方法内可通过，在普通函数内应 fatal
        $this->exec(
            'Cannot use compact("this") outside of class method',
            'compact_no_this.php'
        );
    }
}
