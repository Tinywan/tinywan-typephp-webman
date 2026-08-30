<?php

use PHPUnit\Framework\TestCase;
use TypePhp\CompilerTest;

final class BranchPredictionTest extends TestCase
{
    public function testGeneratesExpectedAndUnexpectedMacros(): void
    {
        global $translator;
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $file = __DIR__ . '/../code/branch-prediction.php';
        $compiler->addFiles([$file]);
        $compiler->prepareFile($file);
        $cppFile = $compiler->convertFile($file);
        $code = file_get_contents($cppFile);

        $this->assertStringContainsString('if (php::toBool(static_cast<bool>(EXPECTED((likely)))))', $code);
        $this->assertStringContainsString(
            'if (php::toBool(static_cast<bool>(UNEXPECTED((php::toBool(unlikely))))))',
            $code,
        );
        $this->assertStringNotContainsString('php_expected(', $code);
        $this->assertStringNotContainsString('php_unexpected(', $code);
    }
}
