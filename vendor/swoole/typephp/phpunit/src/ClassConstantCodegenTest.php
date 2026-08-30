<?php

use TypePhp\CompilerTest;

final class ClassConstantCodegenTest extends \BaseTest
{
    public function testThisConstantIsExpandedButUnknownObjectUsesRuntimeLookup(): void
    {
        $code = $this->compileFixture();

        self::assertMatchesRegularExpression(
            '/php_classconstantcodegen__readthis\([^)]*\)[\s\S]*?= \(23L\);/',
            $code,
        );
        self::assertStringContainsString('php::classConstant(', $code);
        self::assertStringNotContainsString('php::constant(php::concat({', $code);
    }

    private function compileFixture(): string
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/class-constant-codegen.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        return $code;
    }
}
