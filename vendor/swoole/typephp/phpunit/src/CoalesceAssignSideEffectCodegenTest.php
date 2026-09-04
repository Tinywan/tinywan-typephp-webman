<?php

use TypePhp\CompilerTest;

/**
 * PHP evaluates the RHS of ??= only when the target is not set. When the RHS
 * is a compound expression, its lowered statements (the side-effecting call)
 * must be emitted inside the not-set branch, never unconditionally before
 * the isset check.
 */
final class CoalesceAssignSideEffectCodegenTest extends \BaseTest
{
    public function testCompoundRhsCallIsEmittedOnlyInsideNotSetBranch(): void
    {
        $code = $this->compileFixture();

        $body = $this->extractFunctionBody($code, 'php::Int php_coalescecompoundrhs()');

        // The call must appear after the early-return isset guard of the
        // conditional lambda, not as a plain statement before it.
        $callPos = strpos($body, 'php_sideeffectcall()');
        self::assertIsInt($callPos);
        $guardPos = strpos($body, 'if (php::exists(target)) { return target; }');
        self::assertIsInt($guardPos, 'expected the isset guard inside a conditional lambda');
        self::assertGreaterThan($guardPos, $callPos, 'RHS call must be inside the not-set branch');
    }

    public function testSimpleRhsKeepsPlainConditionalExpression(): void
    {
        $code = $this->compileFixture();

        $body = $this->extractFunctionBody($code, 'php::Int php_coalescesimplerhs()');

        self::assertStringContainsString(
            '(php::exists(target)?target:(target = php_sideeffectcall()))',
            $body,
        );
    }

    private function extractFunctionBody(string $code, string $signature): string
    {
        $start = strpos($code, $signature);
        self::assertIsInt($start, "missing function: {$signature}");
        $end = strpos($code, "\n}", $start);
        self::assertIsInt($end);
        return substr($code, $start, $end - $start);
    }

    private function compileFixture(): string
    {
        global $translator;

        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/coalesce-assign-side-effect-codegen.php';
        $compiler->addFiles([$source]);
        $compiler->prepareFile($source);
        $generated = $compiler->convertFile($source);
        $code = file_get_contents($generated);

        self::assertIsString($code);
        return $code;
    }
}
