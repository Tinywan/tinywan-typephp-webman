<?php

use TypePhp\CompilerTest;

/**
 * PHP evaluates call arguments and concat operands left to right. When a
 * later operand hoists captured statements (an assignment), earlier
 * plain-variable reads must be snapshotted at their own position, or the
 * hoisted side effect executes first: pair($j, $j = 5) must return "1,5"
 * and $m . ',' . ($m = 9) must be "1,9". Plain arithmetic is exempt:
 * Zend's ADD opcode reads the CV at op time, so $k + ($k = 5) is 10 in
 * both worlds and must keep its existing codegen.
 */
final class EvalOrderSideEffectsCodegenTest extends \BaseTest
{
    public function testCallArgumentReadIsSnapshottedBeforeLaterAssignment(): void
    {
        $code = $this->compileFixture();
        $body = $this->extractFunctionBody($code, 'php_callargorder()');

        self::assertMatchesRegularExpression(
            '/(tmp_var_\d+) = j;\s*\n\s*(tmp_var_\d+) = j = 5L{1,2};/',
            $body,
            'the old value of $j must be captured before $j = 5 executes',
        );
        self::assertDoesNotMatchRegularExpression(
            '/php_pair\(php::toIntArgExact\(j,/',
            $body,
            '$j must not be read directly after the hoisted assignment',
        );
    }

    public function testConcatOperandReadIsSnapshottedBeforeLaterAssignment(): void
    {
        $code = $this->compileFixture();
        $body = $this->extractFunctionBody($code, 'php_concatorder()');

        self::assertMatchesRegularExpression(
            '/(tmp_var_\d+) = m;\s*\n\s*(tmp_var_\d+) = m = 9L{1,2};/',
            $body,
            'the old value of $m must be captured before $m = 9 executes',
        );
        self::assertDoesNotMatchRegularExpression(
            '/php::concat\(\{php::toString\(m\)/',
            $body,
            '$m must not be read directly after the hoisted assignment',
        );
    }

    public function testCastWrappedAssignmentStillSnapshotsEarlierArgument(): void
    {
        $code = $this->compileFixture();
        $body = $this->extractFunctionBody($code, 'php_castwrappedcallargorder()');

        self::assertMatchesRegularExpression(
            '/(tmp_var_\d+) = i;\s*\n\s*(tmp_var_\d+) = php::toInt\(i = 5L{1,2}\);/',
            $body,
            'the old value of $i must be captured before the cast-wrapped $i = 5 executes',
        );
        self::assertDoesNotMatchRegularExpression(
            '/php_pair\(php::toIntArgExact\(i,/',
            $body,
            '$i must not be read directly alongside the wrapped assignment',
        );
    }

    public function testBooleanNotWrappedAssignmentStillSnapshotsEarlierArgument(): void
    {
        $code = $this->compileFixture();
        $body = $this->extractFunctionBody($code, 'php_notwrappedcallargorder()');

        self::assertMatchesRegularExpression(
            '/(tmp_var_\d+) = k;\s*\n\s*(tmp_var_\d+) = !\(php::toBool\(k = 0L{1,2}\)\);/',
            $body,
            'the old value of $k must be captured before the negated $k = 0 executes',
        );
        self::assertDoesNotMatchRegularExpression(
            '/php_pairvalue\(k,/',
            $body,
            '$k must not be read directly alongside the wrapped assignment',
        );
    }

    public function testPlainArithmeticKeepsZendCvReadSemantics(): void
    {
        $code = $this->compileFixture();
        $body = $this->extractFunctionBody($code, 'php_plainarithmeticunchanged()');

        // Zend reads the CV when the ADD executes, i.e. after the nested
        // assignment; the direct read of k matches that and must stay.
        self::assertMatchesRegularExpression(
            '/(tmp_var_\d+) = k = 5L{1,2};\s*\n[^\n]*\(\(k\) \+ \(\1\)\)/',
            $body,
        );
        self::assertStringNotContainsString('= k;', $body);
    }

    private function extractFunctionBody(string $code, string $marker): string
    {
        $start = strpos($code, $marker);
        self::assertIsInt($start, "missing function: {$marker}");
        $end = strpos($code, "\n}", $start);
        self::assertIsInt($end);
        return substr($code, $start, $end - $start);
    }

    private function compileFixture(): string
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/eval-order-side-effects.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        return $code;
    }
}
