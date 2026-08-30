<?php

use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

class LoopOptimizerTest extends \BaseTest
{
    private function compileToCpp(string $file): string
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $testFile = __DIR__ . '/../code/' . $file;
        $compiler->addFiles([$testFile]);
        $compiler->prepareFile($testFile);
        $compiler->convertFile($testFile);

        $this->addToAssertionCount(1);
        return TYPEPHP_ROOT_PATH . '/build/phpunit/code/' . preg_replace('/\.php$/', '.cc', $file);
    }

    public function testForBoundInternalConstantIsFolded(): void
    {
        try {
            $outputFile = $this->compileToCpp('loop/internal-constant-for-bound.php');
        } catch (TestError $e) {
            $this->fail($e->getMessage());
        }

        $code = file_get_contents($outputFile);
        $this->assertStringNotContainsString('PHP_FD_SETSIZE', $code);
        $this->assertStringNotContainsString('php::constant', $code);
        $this->assertStringContainsString('1024L', $code);
    }
}
