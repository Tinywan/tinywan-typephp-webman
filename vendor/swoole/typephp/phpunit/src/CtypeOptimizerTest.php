<?php

use TypePhp\CompilerTest;

final class CtypeOptimizerTest extends BaseTest
{
    public function testCtypeCallsUseDirectPhpxWrappers(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/ctype-direct-calls.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        foreach ([
            'ctype_alnum',
            'ctype_alpha',
            'ctype_cntrl',
            'ctype_digit',
            'ctype_lower',
            'ctype_graph',
            'ctype_print',
            'ctype_punct',
            'ctype_space',
            'ctype_upper',
            'ctype_xdigit',
        ] as $function) {
            self::assertSame(1, substr_count($code, 'php::fn::' . $function . '('), $function);
        }
        self::assertStringNotContainsString('get_persistent_func', $code);
        self::assertStringNotContainsString('php::call(', $code);
        self::assertStringContainsString(
            'result = php::toBool(php::fn::ctype_alnum(_php__var__char));',
            $code,
        );
    }
}
