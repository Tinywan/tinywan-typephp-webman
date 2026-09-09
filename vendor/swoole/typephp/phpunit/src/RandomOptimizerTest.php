<?php

use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

final class RandomOptimizerTest extends BaseTest
{
    public function testRandomCallsUseDirectPhpxWrappersAndFoldFixedMaximum(): void
    {
        $code = $this->compileFixture('random-direct-calls.php');

        self::assertSame(2, substr_count($code, 'php::fn::mt_rand('));
        self::assertSame(2, substr_count($code, 'php::fn::rand('));
        self::assertSame(1, substr_count($code, 'php::fn::random_int('));
        self::assertSame(1, substr_count($code, 'php::fn::random_bytes('));
        self::assertSame(2, substr_count($code, '2147483647L'));
        self::assertStringNotContainsString('mt_getrandmax', $code);
        self::assertStringNotContainsString('getrandmax', $code);
        self::assertStringNotContainsString('php::call(', $code);
    }

    /** @dataProvider invalidArityProvider */
    public function testMtRandAndRandRejectUnsupportedArgumentCounts(
        string $source,
        string $message,
    ): void {
        $this->expectException(TestError::class);
        $this->expectExceptionMessage($message);
        $this->compileFixture($source);
    }

    public static function invalidArityProvider(): iterable
    {
        yield 'mt_rand with one argument' => [
            'random-invalid-mt-rand-arity.php',
            'mt_rand() expects exactly 0 or 2 arguments, 1 given',
        ];
        yield 'rand with three arguments' => [
            'random-invalid-rand-arity.php',
            'rand() expects exactly 0 or 2 arguments, 3 given',
        ];
        yield 'random_int with one argument' => [
            'random-invalid-random-int-arity.php',
            'random_int() expects at least 2 argument(s), 1 given',
        ];
        yield 'random_bytes with two arguments' => [
            'random-invalid-random-bytes-arity.php',
            'random_bytes() expects at most 1 argument(s), 2 given',
        ];
        yield 'folded mt_getrandmax with an argument' => [
            'random-invalid-getrandmax-arity.php',
            'mt_getrandmax() expects at most 0 argument(s), 1 given',
        ];
    }

    private function compileFixture(string $file): string
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/' . $file;
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);
        self::assertIsString($code);
        return $code;
    }
}
