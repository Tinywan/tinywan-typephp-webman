<?php

use TypePhp\CompilerTest;

/**
 * Typed int/float division, int modulo and int shifts must not be emitted as
 * raw C++ operators: raw zend_long division truncates (PHP: 7 / 2 === 3.5),
 * a zero divisor must raise the catchable DivisionByZeroError instead of
 * being undefined behavior (int) or INF (float), and out-of-range shift
 * counts are undefined behavior in C++ while PHP defines them.
 */
final class TypedScalarArithmeticCodegenTest extends \BaseTest
{
    public function testTypedIntDivisionRoutesThroughVariant(): void
    {
        $code = $this->compileFixture();

        self::assertStringContainsString('((php::Var(a)) / (php::Var(b)))', $code);
        self::assertStringNotContainsString('((a) / (b))', $code);
    }

    public function testTypedIntModuloRoutesThroughPhpMod(): void
    {
        $code = $this->compileFixture();

        self::assertStringContainsString('php::fn::mod(a, b)', $code);
        self::assertStringNotContainsString('((a) % (b))', $code);
    }

    public function testTypedIntShiftsRouteThroughVariant(): void
    {
        $code = $this->compileFixture();

        self::assertStringContainsString('((php::Var(a)) << (php::Var(b)))', $code);
        self::assertStringContainsString('((php::Var(a)) >> (php::Var(b)))', $code);
        self::assertStringNotContainsString('((a) << (b))', $code);
        self::assertStringNotContainsString('((a) >> (b))', $code);
    }

    private function compileFixture(): string
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/typed-scalar-arithmetic-codegen.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        return $code;
    }
}
