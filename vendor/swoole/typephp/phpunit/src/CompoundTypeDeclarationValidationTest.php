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
 * Compound declarations must be rejected by the TypePHP front end, rather
 * than leaking into gen_stub.php or the C++ compiler.
 * @internal
 * @coversNothing
 */
final class CompoundTypeDeclarationValidationTest extends PHPUnit\Framework\TestCase
{
    private string $testRoot;

    protected function setUp(): void
    {
        $this->testRoot = sys_get_temp_dir() . '/typephp-compound-type-' . bin2hex(random_bytes(8));
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

    /** @dataProvider invalidNamedDeclarationProvider */
    public function testInvalidNamedDeclarationFailsDuringPrepare(string $declaration, string $diagnostic): void
    {
        [$compiler, $file] = $this->compilerFor("<?php\n{$declaration}\nfunction main(): void {}\n");

        $this->expectException(TestError::class);
        $this->expectExceptionMessage($diagnostic);
        $compiler->prepareFile($file);
    }

    public static function invalidNamedDeclarationProvider(): iterable
    {
        yield 'duplicate builtin union member' => [
            'function broken(int|string|int $value): void {}',
            'Duplicate type int is redundant',
        ];
        yield 'duplicate resolved class union member' => [
            'namespace App; use Vendor\Model as Item; function broken(Item|\Vendor\Model $value): void {}',
            'Duplicate type Vendor\Model is redundant',
        ];
        yield 'iterable includes array' => [
            'function broken(iterable|array $value): void {}',
            'Duplicate type array is redundant',
        ];
        yield 'iterable includes Traversable' => [
            'function broken(\Traversable|iterable $value): void {}',
            'Duplicate type Traversable is redundant',
        ];
        yield 'bool includes false' => [
            'function broken(false|bool $value): void {}',
            'Duplicate type false is redundant',
        ];
        yield 'true and false must use bool' => [
            'function broken(true|false $value): void {}',
            'Type contains both true and false, bool must be used instead',
        ];
        yield 'mixed in union' => [
            'function broken(mixed|string $value): void {}',
            'Type mixed can only be used as a standalone type',
        ];
        yield 'void in union' => [
            'function broken(): void|string {}',
            'Type void can only be used as a standalone type',
        ];
        yield 'never in union' => [
            'function broken(): never|string {}',
            'Type never can only be used as a standalone type',
        ];
        yield 'nullable mixed' => [
            'function broken(?mixed $value): void {}',
            'Type mixed cannot be marked as nullable since mixed already includes null',
        ];
        yield 'nullable null' => [
            'function broken(?null $value): void {}',
            'null cannot be marked as nullable',
        ];
        yield 'nullable void' => [
            'function broken(): ?void {}',
            'Void can only be used as a standalone type',
        ];
        yield 'nullable never' => [
            'function broken(): ?never {}',
            'never can only be used as a standalone type',
        ];
        yield 'scalar intersection member' => [
            'function broken(A&int $value): void {}',
            'Type int cannot be part of an intersection type',
        ];
        yield 'callable intersection member' => [
            'function broken(A&callable $value): void {}',
            'Type callable cannot be part of an intersection type',
        ];
        yield 'duplicate resolved intersection member' => [
            'namespace App; use Vendor\Contract as C; function broken(C&\Vendor\Contract $value): void {}',
            'Duplicate type Vendor\Contract is redundant',
        ];
        yield 'permuted duplicate DNF member' => [
            'function broken((A&B)|(B&A) $value): void {}',
            'Type B&A is redundant with type A&B',
        ];
        yield 'DNF strict superset after subset' => [
            'function broken((A&B)|(A&B&C) $value): void {}',
            'Type A&B&C is redundant as it is more restrictive than type A&B',
        ];
        yield 'DNF strict superset before subset' => [
            'function broken((A&B&C)|(A&B) $value): void {}',
            'Type A&B&C is redundant as it is more restrictive than type A&B',
        ];
        yield 'plain class subsumes DNF member' => [
            'function broken((A&B)|A $value): void {}',
            'Type A&B is redundant as it is more restrictive than type A',
        ];
        yield 'object subsumes class' => [
            'function broken(object|A $value): void {}',
            'contains both object and a class type, which is redundant',
        ];
        yield 'object subsumes DNF member' => [
            'function broken(object|(A&B) $value): void {}',
            'contains both object and a class type, which is redundant',
        ];
        yield 'self in global function' => [
            'function broken(): self {}',
            'Cannot use "self" when no class scope is active',
        ];
        yield 'self in global function union' => [
            'function broken(): self|A {}',
            'Cannot use "self" when no class scope is active',
        ];
        yield 'self in global function DNF parameter' => [
            'function broken((self&A)|B $value): void {}',
            'Cannot use "self" when no class scope is active',
        ];
        yield 'static in global function' => [
            'function broken(): static {}',
            'Cannot use "static" when no class scope is active',
        ];
        yield 'static in global function union' => [
            'function broken(): static|A {}',
            'Cannot use "static" when no class scope is active',
        ];
        yield 'parent in global function' => [
            'function broken(): parent {}',
            'Cannot use "parent" when no class scope is active',
        ];
        yield 'parent method without parent class' => [
            'class A { public function broken(): parent {} }',
            'Cannot use "parent" when current class scope has no parent',
        ];
        yield 'parent property without parent class' => [
            'class A { public parent $value; }',
            'Cannot use "parent" when current class scope has no parent',
        ];
        yield 'parent constant without parent class' => [
            'class A { public const parent VALUE = null; }',
            'Cannot use "parent" when current class scope has no parent',
        ];
        yield 'self in intersection inside class' => [
            'class A { public function broken(self&B $value): void {} }',
            "Type 'self' cannot be part of an intersection type",
        ];
        yield 'self in DNF promoted property' => [
            'class A { public function __construct(public (self&B)|C $value) {} }',
            "Type 'self' cannot be part of an intersection type",
        ];
        yield 'static in intersection return type' => [
            'class A { public function broken(): static&B {} }',
            "Type 'static' cannot be part of an intersection type",
        ];
        yield 'duplicate class implements' => [
            'interface I {} class A implements I, I {}',
            'Class A cannot implement previously implemented interface I',
        ];
        yield 'duplicate enum implements through alias' => [
            'namespace App; interface I {} use App\I as Contract; enum E implements I, Contract { case A; }',
            'Enum App\E cannot implement previously implemented interface App\I',
        ];
    }

    /** @dataProvider invalidClosureDeclarationProvider */
    public function testInvalidClosureDeclarationFailsDuringConvert(string $body, string $diagnostic): void
    {
        [$compiler, $file] = $this->compilerFor("<?php\nfunction main(): void {\n{$body}\n}\n");
        $compiler->prepareFile($file);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage($diagnostic);
        $compiler->convertFile($file);
    }

    public static function invalidClosureDeclarationProvider(): iterable
    {
        yield 'closure duplicate union' => [
            '$fn = function (int|string|int $value): void {};',
            'Duplicate type int is redundant',
        ];
        yield 'arrow function invalid intersection' => [
            '$fn = fn (A&int $value): int => 1;',
            'Type int cannot be part of an intersection type',
        ];
        yield 'closure permuted DNF' => [
            '$fn = function ((A&B)|(B&A) $value): void {};',
            'Type B&A is redundant with type A&B',
        ];
        yield 'global closure self intersection' => [
            '$fn = function (self&A $value): void {};',
            "Type 'self' cannot be part of an intersection type",
        ];
    }

    public function testValidBoundaryDeclarationsCompile(): void
    {
        [$compiler, $file] = $this->compilerFor(<<<'PHP'
<?php
namespace App;

interface A {}
interface B {}
interface C {}
interface Traversable {}

class Base {}
class Child extends Base
{
    public function choose((A&B)|(A&C)|self|parent $value): (A&B)|(A&C)|self|parent|static
    {
        return $value;
    }
}

function localNameDoesNotOverlapBuiltin(iterable|Traversable $value): void {}
function main(): void {}
PHP);
        $compiler->prepareFile($file);
        $compiler->convertFile($file);

        self::assertFileExists($compiler->getCppFile($file));
    }

    public function testGlobalClosuresKeepBindableSelfAndStaticTypes(): void
    {
        [$compiler, $file] = $this->compilerFor(<<<'PHP'
<?php
function main(): void
{
    $withSelf = function (self $value): self { return $value; };
    $withStatic = function (): static { throw new Exception('not invoked'); };
}
PHP);
        $compiler->prepareFile($file);
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
