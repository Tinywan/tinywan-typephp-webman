<?php

use TypePhp\CompilerTest;

final class DatetimeOptimizerTest extends BaseTest
{
    public function testOnlySupportedDatetimeCallsUseDirectPhpxWrappers(): void
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/datetime-direct-calls.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        self::assertSame(1, substr_count($code, 'php::fn::time('));
        self::assertSame(2, substr_count($code, 'php::fn::date('));
        self::assertSame(1, substr_count($code, 'php::fn::gmdate('));
        self::assertStringNotContainsString('php::fn::strtotime(', $code);
        self::assertSame(1, substr_count($code, 'get_persistent_func'));
        self::assertSame(1, substr_count($code, 'php::call('));
    }
}
