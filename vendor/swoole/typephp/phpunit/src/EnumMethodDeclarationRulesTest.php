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
 * Enum method restrictions are declaration rules. They must fail in the
 * TypePHP front end, including methods injected by Trait composition, rather
 * than reaching Zend class registration during module startup.
 * @internal
 * @coversNothing
 */
final class EnumMethodDeclarationRulesTest extends PHPUnit\Framework\TestCase
{
    private string $testRoot;

    protected function setUp(): void
    {
        $this->testRoot = sys_get_temp_dir() . '/typephp-enum-method-' . bin2hex(random_bytes(8));
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
            if ($entry->isDir()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($this->testRoot);
    }

    /**
     * @dataProvider forbiddenMagicMethodProvider
     */
    public function testForbiddenMagicMethodFailsDuringPrepare(string $method): void
    {
        $source = <<<PHP
<?php
enum Suit
{
    case Hearts;
    public function {$method}() {}
}

function main(): void {}
PHP;

        [$compiler, $file] = $this->compilerFor($source);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage("Enum `Suit` cannot include magic method `{$method}`");
        $compiler->prepareFile($file);
    }

    public static function forbiddenMagicMethodProvider(): iterable
    {
        foreach ([
            '__construct',
            '__destruct',
            '__clone',
            '__get',
            '__set',
            '__unset',
            '__isset',
            '__sleep',
            '__wakeup',
            '__set_state',
            '__serialize',
            '__unserialize',
            '__ToString',
            '__debugInfo',
        ] as $method) {
            yield $method => [$method];
        }
    }

    public function testTraitInjectedForbiddenMethodFailsDuringComposition(): void
    {
        $source = <<<'PHP'
<?php
trait Builder
{
    public function __construct() {}
}

enum Suit
{
    use Builder;
    case Hearts;
}

function main(): void {}
PHP;

        [$compiler, $file] = $this->compilerFor($source);
        $compiler->prepareFile($file);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Enum `Suit` cannot include magic method `__construct`');
        $compiler->composeTraitDeclarations([$file]);
    }

    public function testTraitAliasToForbiddenMethodFailsDuringComposition(): void
    {
        $source = <<<'PHP'
<?php
trait Builder
{
    public function cleanup(): void {}
}

enum Suit
{
    use Builder { cleanup as __destruct; }
    case Hearts;
}

function main(): void {}
PHP;

        [$compiler, $file] = $this->compilerFor($source);
        $compiler->prepareFile($file);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Enum `Suit` cannot include magic method `__destruct`');
        $compiler->composeTraitDeclarations([$file]);
    }

    /** @dataProvider reservedMethodProvider */
    public function testEnumCannotRedeclareBuiltinMethod(string $declaration, string $method): void
    {
        [$compiler, $file] = $this->compilerFor("<?php\n{$declaration}\nfunction main(): void {}\n");

        $this->expectException(TestError::class);
        $this->expectExceptionMessage("Cannot redeclare Status::{$method}()");
        $compiler->prepareFile($file);
    }

    public static function reservedMethodProvider(): iterable
    {
        yield 'cases on pure enum' => [
            'enum Status { case Active; public static function cases(): array { return []; } }',
            'cases',
        ];
        yield 'from on backed enum' => [
            'enum Status: string { case Active = "active"; public static function from(string $value): self { return self::Active; } }',
            'from',
        ];
        yield 'tryFrom is case insensitive' => [
            'enum Status: int { case Active = 1; public static function TRYFROM(int $value): ?self { return null; } }',
            'TRYFROM',
        ];
    }

    public function testPureEnumMayDeclareFromAndTryFrom(): void
    {
        [$compiler, $file] = $this->compilerFor(<<<'PHP'
<?php
enum Status {
    case Active;
    public static function from(string $value): self { return self::Active; }
    public static function tryFrom(string $value): ?self { return null; }
}
function main(): void {}
PHP);
        $compiler->prepareFile($file);
        $compiler->convertFile($file);

        self::assertFileExists($compiler->getCppFile($file));
    }

    public function testTraitCannotInjectReservedEnumMethod(): void
    {
        [$compiler, $file] = $this->compilerFor(<<<'PHP'
<?php
trait ListsCases { public static function listAll(): array { return []; } }
enum Status { use ListsCases { listAll as cases; } case Active; }
function main(): void {}
PHP);
        $compiler->prepareFile($file);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Cannot redeclare Status::cases()');
        $compiler->composeTraitDeclarations([$file]);
    }

    public function testEnumCannotDeclareAbstractMethod(): void
    {
        $source = <<<'PHP'
<?php
enum Suit
{
    case Hearts;
    abstract public function label(): string;
}

function main(): void {}
PHP;

        [$compiler, $file] = $this->compilerFor($source);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Enum method Suit::label() must not be abstract');
        $compiler->prepareFile($file);
    }

    public function testEnumMustImplementAbstractMethodImportedFromTrait(): void
    {
        $source = <<<'PHP'
<?php
trait Labeled
{
    abstract public function label(): string;
}

enum Suit
{
    use Labeled;
    case Hearts;
}

function main(): void {}
PHP;

        [$compiler, $file] = $this->compilerFor($source);
        $compiler->prepareFile($file);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Enum Suit must implement 1 abstract method (Suit::label)');
        $compiler->convertFile($file);
    }

    public function testEnumMayImplementAbstractTraitMethod(): void
    {
        $source = <<<'PHP'
<?php
trait Labeled
{
    abstract public function label(): string;
}

enum Suit
{
    use Labeled;
    case Hearts;

    public function label(): string
    {
        return $this->name;
    }
}

function main(): void {}
PHP;

        [$compiler, $file] = $this->compilerFor($source);
        $compiler->prepareFile($file);
        $compiler->convertFile($file);

        self::assertFileExists($compiler->getCppFile($file));
    }

    public function testEnumMayImplementInterface(): void
    {
        $source = <<<'PHP'
<?php
interface Labeled
{
    public function label(): string;
}

enum Suit implements Labeled
{
    case Hearts;

    public function label(): string
    {
        return $this->name;
    }
}

function main(): void {}
PHP;

        [$compiler, $file] = $this->compilerFor($source);
        $compiler->prepareFile($file);
        $compiler->convertFile($file);

        self::assertFileExists($compiler->getCppFile($file));
    }

    public function testEnumImplicitInterfacesParticipateInTypeCompatibility(): void
    {
        [$compiler, $file] = $this->compilerFor(<<<'PHP'
<?php
interface PureProvider { public function get(): UnitEnum; }
interface BackedProvider { public function get(): BackedEnum; }
enum PureStatus implements PureProvider {
    case Active;
    public function get(): self { return self::Active; }
}
enum HttpStatus: int implements BackedProvider {
    case Ok = 200;
    public function get(): self { return self::Ok; }
}
function main(): void {}
PHP);
        $compiler->prepareFile($file);
        $compiler->convertFile($file);

        self::assertFileExists($compiler->getCppFile($file));
    }

    public function testBuiltinCasesSatisfiesUserInterface(): void
    {
        [$compiler, $file] = $this->compilerFor(<<<'PHP'
<?php
interface ListsCases { public static function cases(): array; }
enum Status implements ListsCases { case Active; }
function main(): void {}
PHP);
        $compiler->prepareFile($file);
        $compiler->convertFile($file);

        self::assertFileExists($compiler->getCppFile($file));
    }

    /** @dataProvider incompatibleBuiltinContractProvider */
    public function testBuiltinEnumMethodMustSatisfyInterfaceSignature(string $source, string $method): void
    {
        [$compiler, $file] = $this->compilerFor("<?php\n{$source}\nfunction main(): void {}\n");
        $compiler->prepareFile($file);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage("Declaration of `Status::{$method}()` must be compatible");
        $compiler->convertFile($file);
    }

    public static function incompatibleBuiltinContractProvider(): iterable
    {
        yield 'cases has an argument' => [
            'interface Contract { public static function cases(int $extra): array; } enum Status implements Contract { case Active; }',
            'cases',
        ];
        yield 'cases must be static' => [
            'interface Contract { public function cases(): array; } enum Status implements Contract { case Active; }',
            'cases',
        ];
        yield 'from cannot accept bool contract' => [
            'interface Contract { public static function from(bool $value): mixed; } enum Status: int implements Contract { case Active = 1; }',
            'from',
        ];
    }

    public function testEnumMustImplementInterfaceMethod(): void
    {
        $source = <<<'PHP'
<?php
interface Labeled
{
    public function label(): string;
}

enum Suit implements Labeled
{
    case Hearts;
}

function main(): void {}
PHP;

        [$compiler, $file] = $this->compilerFor($source);
        $compiler->prepareFile($file);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Enum Suit must implement 1 abstract method (Labeled::label)');
        $compiler->convertFile($file);
    }

    public function testEnumReportsTraitAndInterfaceAbstractMethodsTogether(): void
    {
        $source = <<<'PHP'
<?php
trait Labeled
{
    abstract public function label(): string;
}

interface SerializableName
{
    public function serializedName(): string;
}

enum Suit implements SerializableName
{
    use Labeled;
    case Hearts;
}

function main(): void {}
PHP;

        [$compiler, $file] = $this->compilerFor($source);
        $compiler->prepareFile($file);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage(
            'Enum Suit must implement 2 abstract methods (Suit::label, SerializableName::serializedName)',
        );
        $compiler->convertFile($file);
    }

    public function testCallCallStaticAndInvokeRemainAllowed(): void
    {
        $source = <<<'PHP'
<?php
enum Suit
{
    case Hearts;

    public function __call(string $name, array $arguments): mixed
    {
        return null;
    }

    public static function __callStatic(string $name, array $arguments): mixed
    {
        return null;
    }

    public function __invoke(): string
    {
        return 'Hearts';
    }
}

function main(): void {}
PHP;

        [$compiler, $file] = $this->compilerFor($source);
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
