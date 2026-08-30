<?php

use PHPUnit\Framework\TestCase;
use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

require __DIR__ . '/../bin/bootstrap.php';
require_once __DIR__ . '/../src/polyfills.php';
require __DIR__ . '/../src/gen_stub.php';

class BaseTest extends TestCase
{
    protected function compile(string $file): void
    {
        global $translator;
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $testFile = __DIR__ . '/code/' . $file;
        $compiler->addFiles([$testFile]);
        $compiler->prepareFile($testFile);
        $compiler->convertFile($testFile);
        $this->addToAssertionCount(1);
    }

    protected function exec(string $expected, string $file): void
    {
        try {
            $this->compile($file);
        } catch (TestError $exception) {
            $this->assertStringContainsString($expected, $exception->getMessage());
            return;
        }
        $this->fail();
    }
}
