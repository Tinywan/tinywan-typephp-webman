<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

/**
 * Backed enum case expressions are folded after declaration composition and
 * before C++ generation. No source expression may survive into runtime code.
 * @internal
 * @coversNothing
 */
final class EnumCaseConstantExpressionTest extends PHPUnit\Framework\TestCase
{
    public function testExpressionsAreFinalizedBeforeCodeGeneration(): void
    {
        global $translator;
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $file = TYPEPHP_ROOT_PATH . '/phpunit/code/enum-case-constant-expressions.php';
        $compiler->addFiles([$file]);
        $compiler->prepareFile($file);

        $number = $compiler->getClassDef('EnumExpressionFixture\\Number');
        self::assertNotNull($number);
        self::assertNull($number->enumCases['Two']);
        self::assertArrayHasKey('Two', $number->enumCaseExpressions);

        $compiler->convertFile($file);

        self::assertSame([
            'Two' => 2,
            'Three' => 3,
            'Four' => 4,
            'Five' => 5,
            'Six' => 6,
            'Seven' => 7,
            'Eight' => 8,
        ], $number->enumCases);
        self::assertSame([], $number->enumCaseExpressions);

        $word = $compiler->getClassDef('EnumExpressionFixture\\Word');
        self::assertNotNull($word);
        self::assertSame([
            'Hello' => 'hello',
            'CaseName' => 'Two',
        ], $word->enumCases);
        self::assertSame([], $word->enumCaseExpressions);

        $header = file_get_contents($compiler->getArgInfoHeaderFile($file));
        self::assertIsString($header);
        self::assertStringContainsString('ZVAL_LONG(&enum_case_Six_value, 6);', $header);
        self::assertStringContainsString('zend_string_init_interned("hello"', $header);
        self::assertStringNotContainsString('php::getEnumCase', $header);
    }

    /**
     * @dataProvider invalidExpressionProvider
     */
    public function testInvalidExpressionFailsBeforeCodeGeneration(
        string $expression,
        string $expected,
        string $declarations = '',
        string $additionalCases = '',
    ): void {
        $root = sys_get_temp_dir() . '/typephp-enum-expression-' . bin2hex(random_bytes(8));
        mkdir($root, 0777, true);
        $file = $root . '/program.php';
        file_put_contents($file, <<<PHP
<?php
{$declarations}
enum InvalidEnum: int
{
    case A = {$expression};
    {$additionalCases}
}

function main(): void {}
PHP);

        try {
            global $translator;
            $compiler = CompilerTest::create($root);
            $translator = $compiler;
            $compiler->addFiles([$file]);
            $compiler->prepareFile($file);

            try {
                $compiler->convertFile($file);
                self::fail('Compilation unexpectedly succeeded');
            } catch (TestError $error) {
                self::assertStringContainsString($expected, $error->getMessage());
            }
            self::assertFileDoesNotExist($compiler->getCppFile($file));
        } finally {
            $this->removeTree($root);
        }
    }

    public static function invalidExpressionProvider(): iterable
    {
        yield 'unknown runtime constant' => [
            'RUNTIME_VALUE',
            'backing value must be compile-time evaluable: Constant `RUNTIME_VALUE` is not known at compile time',
        ];
        yield 'wrong scalar result type' => [
            '1 / 2',
            'backing value must be of type int, float given',
        ];
        yield 'self-reference through enum value' => [
            'self::A->value + 1',
            'Cannot declare self-referencing constant `InvalidEnum::A`',
        ];
        yield 'self-reference through enum name' => [
            'self::A->name === "A" ? 1 : 2',
            'Cannot declare self-referencing constant `InvalidEnum::A`',
        ];
        yield 'class constant cycle' => [
            'Cycle::A',
            'Cannot declare self-referencing constant `Cycle::A`',
            'class Cycle { public const A = self::B; public const B = self::A; }',
        ];
        yield 'global constant cycle' => [
            'FIRST',
            'Cannot declare self-referencing constant `FIRST`',
            'const FIRST = SECOND; const SECOND = FIRST;',
        ];
        yield 'mutually recursive enum cases' => [
            'self::B->value + 1',
            'Cannot declare self-referencing constant `InvalidEnum::A`',
            '',
            'case B = self::A->value + 1;',
        ];
        yield 'unknown enum case' => [
            'self::Missing->value',
            'Class constant `InvalidEnum::Missing` not found',
        ];
        yield 'evaluation error' => [
            '1 / 0',
            'backing value must be compile-time evaluable: Division by zero',
        ];
    }

    private function removeTree(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($root);
    }
}
