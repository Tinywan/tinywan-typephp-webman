<?php

use TypePhp\CompilerTest;

final class MagicCallCodegenTest extends \BaseTest
{
    public function testOnlyExactReceiverBypassesZendMagicCallTrampoline(): void
    {
        $code = $this->compileFixture();

        $exactBody = $this->functionBody($code, 'php_exactmagiccall');
        self::assertStringContainsString('php_exactmagichandler____call(', $exactBody);
        self::assertStringNotContainsString('.call(', $exactBody);

        $runtimeBody = $this->functionBody($code, 'php_runtimemagiccall');
        self::assertStringContainsString('typephp_call_method_cached(', $runtimeBody);
        self::assertStringNotContainsString('php_exactmagichandler____call(', $runtimeBody);

        $internalBody = $this->functionBody($code, 'php_exactinternalmethod');
        self::assertStringContainsString('typephp_call_method_cached(', $internalBody);
        self::assertStringNotContainsString('__call(', $internalBody);
    }

    private function compileFixture(): string
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/magic-call-codegen.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        return $code;
    }

    private function functionBody(string $code, string $function): string
    {
        $matched = preg_match('/php::Var ' . preg_quote($function, '/') . '\\([^)]*\\) \\{(?<body>.*?)\\n\\}/s', $code, $match);
        self::assertSame(1, $matched, "generated body of {$function}() not found");
        return $match['body'];
    }
}
