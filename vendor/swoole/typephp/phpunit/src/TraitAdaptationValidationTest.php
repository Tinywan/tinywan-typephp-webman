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
 * @internal
 * @coversNothing
 */
final class TraitAdaptationValidationTest extends PHPUnit\Framework\TestCase
{
    private string $testRoot;

    protected function setUp(): void
    {
        $this->testRoot = sys_get_temp_dir() . '/typephp-trait-adaptation-' . bin2hex(random_bytes(8));
        mkdir($this->testRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->testRoot)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->testRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->testRoot);
    }

    /** @dataProvider invalidAdaptationProvider */
    public function testInvalidAdaptationIsRejectedDuringComposition(string $body, string $message): void
    {
        [$compiler, $file] = $this->compilerFor("<?php\n{$body}\nfunction main(): void {}\n");
        $compiler->prepareFile($file);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage($message);
        $compiler->composeTraitDeclarations([$file]);
    }

    public static function invalidAdaptationProvider(): iterable
    {
        yield 'unqualified alias names a missing method' => [
            'trait A { public function f(): void {} } class C { use A { missing as renamed; } }',
            'alias was defined for method `missing()`',
        ];
        yield 'trait declared after consumer is still validated' => [
            'class C { use A { missing as renamed; } } trait A { public function f(): void {} }',
            'alias was defined for method `missing()`',
        ];
        yield 'qualified alias names a missing method' => [
            'trait A { public function f(): void {} } class C { use A { A::missing as renamed; } }',
            'alias was defined for method `A::missing()`',
        ];
        yield 'alias names a trait not used by the class' => [
            'trait A { public function f(): void {} } trait B { public function f(): void {} } class C { use A { B::f as renamed; } }',
            "Required Trait `B` wasn't added to `C`",
        ];
        yield 'precedence winner has no method' => [
            'trait A {} trait B { public function f(): void {} } class C { use A, B { A::f insteadof B; } }',
            'precedence rule was defined for `A::f()`',
        ];
        yield 'precedence winner trait is not used' => [
            'trait A { public function f(): void {} } trait B { public function f(): void {} } class C { use B { A::f insteadof B; } }',
            "Required Trait `A` wasn't added to `C`",
        ];
        yield 'precedence loser trait is not used' => [
            'trait A { public function f(): void {} } trait B { public function f(): void {} } class C { use A { A::f insteadof B; } }',
            "Required Trait `B` wasn't added to `C`",
        ];
        yield 'same method is excluded twice' => [
            'trait A { public function f(): void {} } trait B { public function f(): void {} } class C { use A, B { A::f insteadof B; A::f insteadof B; } }',
            'was excluded multiple times',
        ];
        yield 'unqualified alias is ambiguous' => [
            'trait A { public function f(): void {} } trait B { public function f(): void {} } class C { use A, B { f as renamed; A::f insteadof B; } }',
            'exists in multiple traits',
        ];
    }

    public function testValidAdaptationsRemainSupported(): void
    {
        [$compiler, $file] = $this->compilerFor(<<<'PHP'
<?php
trait A { public function f(): void {} }
trait B {}
trait D { public function f(): void {} }
class C {
    use A;
    use B, D {
        A::f insteadof B, D;
        D::f as other;
    }
}
function main(): void {}
PHP);
        $compiler->prepareFile($file);
        $compiler->composeTraitDeclarations([$file]);
        $compiler->convertFile($file);

        self::assertFileExists($compiler->getCppFile($file));
    }

    public function testAliasCanTargetMethodFromNestedTrait(): void
    {
        [$compiler, $file] = $this->compilerFor(<<<'PHP'
<?php
trait Inner { public function f(): void {} }
trait Outer { use Inner; }
class C { use Outer { f as renamed; } }
function main(): void {}
PHP);
        $compiler->prepareFile($file);
        $compiler->composeTraitDeclarations([$file]);
        $compiler->convertFile($file);

        self::assertFileExists($compiler->getCppFile($file));
    }

    /** @return array{CompilerTest, string} */
    private function compilerFor(string $source): array
    {
        $file = $this->testRoot . '/program.php';
        file_put_contents($file, $source);

        global $translator;
        $compiler = CompilerTest::create($this->testRoot);
        $translator = $compiler;
        $compiler->addFiles([$file]);

        return [$compiler, $file];
    }
}
