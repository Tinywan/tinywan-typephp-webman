<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

use PhpParser\Modifiers;
use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

/**
 * @internal
 * @coversNothing
 */
final class EnumDeclarationRulesTest extends PHPUnit\Framework\TestCase
{
    private string $testRoot;

    protected function setUp(): void
    {
        $this->testRoot = sys_get_temp_dir() . '/typephp-enum-rules-' . bin2hex(random_bytes(8));
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

    public function testDuplicateCaseIsRejectedDuringPrepare(): void
    {
        $compiler = $this->compilerFor(<<<'PHP'
<?php
enum Suit { case Hearts; case Hearts; }
function main(): void {}
PHP);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Cannot redefine class constant Suit::Hearts');
        $compiler->prepareFile($this->testRoot . '/program.php');
    }

    /** @dataProvider caseConstantClashProvider */
    public function testCaseAndClassConstantCannotShareAName(string $members): void
    {
        $compiler = $this->compilerFor("<?php\nenum Suit { {$members} }\nfunction main(): void {}\n");

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Cannot redefine class constant Suit::Hearts');
        $compiler->prepareFile($this->testRoot . '/program.php');
    }

    public static function caseConstantClashProvider(): iterable
    {
        yield 'case then constant' => ['case Hearts; public const Hearts = 1;'];
        yield 'constant then case' => ['public const Hearts = 1; case Hearts;'];
    }

    /** @dataProvider duplicateBackingValueProvider */
    public function testDuplicateBackingValueIsRejectedAfterConstantEvaluation(
        string $declarations,
        string $enum,
        string $expectedCases,
    ): void {
        $compiler = $this->compilerFor(<<<PHP
<?php
{$declarations}
{$enum}
function main(): void {}
PHP);
        $file = $this->testRoot . '/program.php';
        $compiler->prepareFile($file);

        try {
            $compiler->convertFile($file);
            self::fail('Compilation unexpectedly succeeded');
        } catch (TestError $error) {
            self::assertStringContainsString(
                "Duplicate value in enum Code for cases {$expectedCases}",
                $error->getMessage(),
            );
        }
        self::assertFileDoesNotExist($compiler->getCppFile($file));
    }

    public static function duplicateBackingValueProvider(): iterable
    {
        yield 'integer literals' => [
            '',
            'enum Code: int { case First = 1; case Second = 1; }',
            'First and Second',
        ];
        yield 'integer constant expression' => [
            '',
            'enum Code: int { case First = 1 + 1; case Second = 2; }',
            'First and Second',
        ];
        yield 'negative zero folds to zero' => [
            '',
            'enum Code: int { case First = -0; case Second = 0; }',
            'First and Second',
        ];
        yield 'string constant expression' => [
            '',
            "enum Code: string { case First = 'type' . 'php'; case Second = 'typephp'; }",
            'First and Second',
        ];
        yield 'global and class constants' => [
            'const VALUE = 4; class Provider { public const VALUE = VALUE; }',
            'enum Code: int { case First = VALUE; case Second = Provider::VALUE; }',
            'First and Second',
        ];
        yield 'enum case value reference' => [
            '',
            'enum Code: int { case First = 5; case Second = self::First->value; }',
            'First and Second',
        ];
        yield 'forward reference retains source order' => [
            '',
            'enum Code: int { case First = self::Third->value + 1; case Second = 7; case Third = 6; }',
            'First and Second',
        ];
    }

    public function testDistinctNumericStringsRemainDistinct(): void
    {
        $compiler = $this->compilerFor(<<<'PHP'
<?php
enum Code: string { case One = '1'; case ZeroOne = '01'; }
function main(): void {}
PHP);
        $file = $this->testRoot . '/program.php';
        $compiler->prepareFile($file);
        $compiler->convertFile($file);

        self::assertSame(
            ['One' => '1', 'ZeroOne' => '01'],
            $compiler->getClassDef('Code')?->enumCases,
        );
    }

    public function testCaseNamesRemainCaseSensitive(): void
    {
        $compiler = $this->compilerFor(<<<'PHP'
<?php
enum Suit { case Hearts; case hearts; }
function main(): void {}
PHP);
        $file = $this->testRoot . '/program.php';
        $compiler->prepareFile($file);
        $compiler->convertFile($file);

        self::assertSame(['Hearts' => null, 'hearts' => null], $compiler->getClassDef('Suit')?->enumCases);
    }

    public function testEnumIsImplicitlyFinal(): void
    {
        $compiler = $this->compilerFor(<<<'PHP'
<?php
enum Suit { case Hearts; }
function main(): void {}
PHP);
        $compiler->prepareFile($this->testRoot . '/program.php');

        $enum = $compiler->getClassDef('Suit');
        self::assertNotNull($enum);
        self::assertNotSame(0, $enum->flags & Modifiers::FINAL);
    }

    public function testClassCannotExtendEnum(): void
    {
        $compiler = $this->compilerFor(<<<'PHP'
<?php
enum Suit { case Hearts; }
class InvalidSuit extends Suit {}
function main(): void {}
PHP);
        $file = $this->testRoot . '/program.php';
        $compiler->prepareFile($file);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('cannot extend final class `Suit`');
        $compiler->convertFile($file);
    }

    public function testEnumCannotDeclareProperty(): void
    {
        $compiler = $this->compilerFor(<<<'PHP'
<?php
enum Suit { case Hearts; public string $label; }
function main(): void {}
PHP);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Enum Suit cannot include properties');
        $compiler->prepareFile($this->testRoot . '/program.php');
    }

    public function testEnumCannotImportTraitProperty(): void
    {
        $compiler = $this->compilerFor(<<<'PHP'
<?php
trait HasLabel { public string $label; }
enum Suit { use HasLabel; case Hearts; }
function main(): void {}
PHP);
        $file = $this->testRoot . '/program.php';
        $compiler->prepareFile($file);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Enum Suit cannot include properties');
        $compiler->composeTraitDeclarations([$file]);
    }

    public function testEnumBackingTypeMustBeIntOrString(): void
    {
        $compiler = $this->compilerFor(<<<'PHP'
<?php
enum Flag: bool { case Enabled = true; }
function main(): void {}
PHP);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Enum backing type must be int or string, bool given');
        $compiler->prepareFile($this->testRoot . '/program.php');
    }

    public function testAllowDynamicPropertiesCannotBeAppliedToEnum(): void
    {
        $compiler = $this->compilerFor(<<<'PHP'
<?php
#[AllowDynamicProperties]
enum Suit { case Hearts; }
function main(): void {}
PHP);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Cannot apply #[AllowDynamicProperties] to enum `Suit`');
        $compiler->prepareFile($this->testRoot . '/program.php');
    }

    /** @dataProvider forbiddenEnumInterfaceProvider */
    public function testEnumCannotExplicitlyImplementReservedInterface(
        string $declaration,
        string $message,
    ): void {
        $compiler = $this->compilerFor("<?php\n{$declaration}\nfunction main(): void {}\n");

        $this->expectException(TestError::class);
        $this->expectExceptionMessage($message);
        $compiler->prepareFile($this->testRoot . '/program.php');
    }

    public static function forbiddenEnumInterfaceProvider(): iterable
    {
        yield 'pure UnitEnum' => [
            'enum Suit implements UnitEnum { case Hearts; }',
            'Enum Suit cannot implement previously implemented interface UnitEnum',
        ];
        yield 'pure BackedEnum' => [
            'enum Suit implements BackedEnum { case Hearts; }',
            'Non-backed enum Suit cannot implement interface BackedEnum',
        ];
        yield 'backed BackedEnum' => [
            'enum Suit: string implements BackedEnum { case Hearts = "hearts"; }',
            'Enum Suit cannot implement previously implemented interface BackedEnum',
        ];
        yield 'Serializable' => [
            'enum Suit implements Serializable { case Hearts; public function serialize(): string { return ""; } public function unserialize(string $data): void {} }',
            'Enum Suit cannot implement interface Serializable',
        ];
    }

    public function testEnumCannotImplementSerializableTransitively(): void
    {
        $compiler = $this->compilerFor(<<<'PHP'
<?php
interface LegacySerializable extends Serializable {}
enum Suit implements LegacySerializable {
    case Hearts;
    public function serialize(): string { return ''; }
    public function unserialize(string $data): void {}
}
function main(): void {}
PHP);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Enum Suit cannot implement interface Serializable');
        $compiler->prepareFile($this->testRoot . '/program.php');
    }

    public function testOrdinaryClassCannotImplementUnitEnum(): void
    {
        $compiler = $this->compilerFor(<<<'PHP'
<?php
class FakeEnum implements UnitEnum { public static function cases(): array { return []; } }
function main(): void {}
PHP);
        $file = $this->testRoot . '/program.php';
        $compiler->prepareFile($file);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Class FakeEnum cannot implement interface UnitEnum');
        $compiler->convertFile($file);
    }

    public function testEnumCaseCannotInitializeScalarTypedProperty(): void
    {
        $compiler = $this->compilerFor(<<<'PHP'
<?php
enum Code: int { case Ok = 200; }
class Response { public int $code = Code::Ok; }
function main(): void {}
PHP);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Cannot use Code as default value for property Response::$code of type int');
        $compiler->prepareFile($this->testRoot . '/program.php');
    }

    public function testEnumCaseMayInitializeCompatibleObjectProperty(): void
    {
        $compiler = $this->compilerFor(<<<'PHP'
<?php
enum Code: int { case Ok = 200; }
class Response { public UnitEnum|Code $code = Code::Ok; }
function main(): void {}
PHP);
        $file = $this->testRoot . '/program.php';
        $compiler->prepareFile($file);
        $compiler->convertFile($file);

        self::assertFileExists($compiler->getCppFile($file));
    }

    private function compilerFor(string $source): CompilerTest
    {
        $file = $this->testRoot . '/program.php';
        file_put_contents($file, $source);

        global $translator;
        $compiler = CompilerTest::create($this->testRoot);
        $translator = $compiler;
        $compiler->addFiles([$file]);
        return $compiler;
    }
}
