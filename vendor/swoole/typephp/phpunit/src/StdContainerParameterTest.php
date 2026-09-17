<?php

use PHPUnit\Framework\Attributes\DataProvider;
use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

final class StdContainerParameterTest extends BaseTest
{
    public function testLibraryStubPreservesContainerContracts(): void
    {
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $generator = new \TypePhp\Generator\LibraryImportStubGenerator(
            (new ReflectionProperty($compiler, 'parser'))->getValue($compiler),
            (new ReflectionProperty($compiler, 'printer'))->getValue($compiler),
        );
        $code = $generator->generate([TYPEPHP_ROOT_PATH . '/phpunit/code/std-container-parameters.php'], []);
        self::assertStringContainsString('StdVector(', $code);
        self::assertStringContainsString('StdArray(', $code);
        self::assertStringContainsString('StdMap(', $code);
        self::assertStringContainsString('StdOrderedMap(', $code);
        self::assertStringContainsString('Type::Int', $code);
        self::assertStringContainsString('StdParameterUser::class', $code);
    }

    public function testBoxAbiAndNativeContainerBindings(): void
    {
        $this->compile('std-container-parameters.php');
        global $translator;
        $source = TYPEPHP_ROOT_PATH . '/phpunit/code/std-container-parameters.php';
        $code = file_get_contents($translator->getCppFile($source));
        self::assertStringContainsString('php_std_parameter_vector(php::Var vec)', $code);
        self::assertStringContainsString('auto &vec_ref = php::toStdContainer<php::StdVector<php::Int>>(vec, ', $code);
        self::assertStringContainsString('auto &matrix_ref = php::toStdContainer<php::StdArray<php::StdArray<php::Int, 3>, 2>>(matrix, ', $code);
        self::assertStringContainsString('auto &map_ref = php::toStdContainer<', $code);
        self::assertStringContainsString('auto &users_ref = php::toStdContainer<', $code);
        self::assertStringNotContainsString('php::Var vec;', $code);
        $function = (new ReflectionMethod($translator, 'getFunction'))->invoke($translator, 'std_parameter_vector');
        $parameter = $function->argInfoList[0];
        self::assertFalse($parameter->undeclared);
        self::assertFalse($parameter->nullable);
        self::assertSame('vector', $parameter->stdContainer['kind']);
        self::assertArrayNotHasKey('typeId', $parameter->stdContainer);
        self::assertSame($parameter->stdContainer, unserialize(serialize($parameter))->stdContainer);
        $matrix = (new ReflectionMethod($translator, 'getFunction'))->invoke($translator, 'std_parameter_matrix')->argInfoList[0];
        self::assertSame('array', $matrix->stdContainer['kind']);
        self::assertSame([2, 3], $matrix->stdContainer['dimensions']);
        self::assertSame([3, 2], $matrix->stdContainer['sizes']);
    }

    #[DataProvider('invalidDeclarations')]
    public function testInvalidDeclarations(string $source, string $message): void
    {
        $directory = sys_get_temp_dir() . '/std-parameter-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $file = $directory . '/invalid.php';
        file_put_contents($file, '<?php ' . $source);
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        global $translator;
        $translator = $compiler;
        try {
            $compiler->addFiles([$file]);
            $compiler->prepareFile($file);
            $compiler->convertFile($file);
            self::fail('Invalid container parameter was accepted');
        } catch (TestError|\TypePhp\Exception\SyntaxError $error) {
            self::assertStringContainsString($message, $error->getMessage());
        } finally {
            unlink($file);
            rmdir($directory);
        }
    }

    public static function invalidDeclarations(): iterable
    {
        yield 'mixed conflict' => ['function foo(#[StdVector(Type::Int)] mixed $vec): void {}', 'a PHP type cannot also be declared'];
        yield 'array conflict' => ['function foo(#[StdMap(Type::Int, Type::Int)] array $vec): void {}', 'a PHP type cannot also be declared'];
        yield 'duplicate' => ['function foo(#[StdVector(Type::Int), StdVector(Type::Int)] $vec): void {}', 'cannot be repeated'];
        yield 'two containers' => ['function foo(#[StdVector(Type::Int), StdMap(Type::Int, Type::Int)] $vec): void {}', 'cannot be applied to the same declaration'];
        yield 'missing type' => ['function foo(#[StdVector] $vec): void {}', 'expects 1 type argument'];
        yield 'vector size' => ['function foo(#[StdVector(Type::Int, 3)] $vec): void {}', 'expects 1 type argument'];
        yield 'array missing dimensions' => ['function foo(#[StdArray(Type::Int)] $value): void {}', 'expects an element type'];
        yield 'array empty dimensions' => ['function foo(#[StdArray(Type::Int, [])] $value): void {}', 'dimensions cannot be empty'];
        yield 'array dynamic dimensions' => ['function foo(#[StdArray(Type::Int, SIZE)] $value): void {}', 'expects an integer size'];
        yield 'array keyed dimensions' => ['function foo(#[StdArray(Type::Int, [0 => 2])] $value): void {}', 'positional array'];
        yield 'array negative dimension' => ['function foo(#[StdArray(Type::Int, [-1])] $value): void {}', 'integer literals'];
        yield 'map missing value' => ['function foo(#[StdMap(Type::Int)] $vec): void {}', 'expects 2 type argument'];
        yield 'invalid key' => ['function foo(#[StdMap(Type::Float, Type::Int)] $vec): void {}', 'key only supports Type::Int or Type::String'];
        yield 'literal argument' => ['function foo(#[StdVector("int")] $vec): void {}', 'expects a Type constant'];
        yield 'named argument' => ['function foo(#[StdVector(valueType: Type::Int)] $vec): void {}', 'requires positional type arguments'];
        yield 'reference' => ['function foo(#[StdVector(Type::Int)] &$vec): void {}', 'does not support reference'];
        yield 'variadic' => ['function foo(#[StdVector(Type::Int)] ...$vec): void {}', 'does not support reference'];
        yield 'default' => ['function foo(#[StdVector(Type::Int)] $vec = null): void {}', 'does not support reference'];
        yield 'closure' => ['$fn = function(#[StdVector(Type::Int)] $vec) {};', 'named function or method parameters'];
        yield 'arrow' => ['$fn = fn(#[StdVector(Type::Int)] $vec) => 1;', 'named function or method parameters'];
        yield 'wrong target' => ['#[StdVector(Type::Int)] function foo(): void {}', 'can only be applied'];
        yield 'generator' => ['function foo(#[StdVector(Type::Int)] $vec) { yield 1; }', 'not supported on generators'];
        yield 'native elements' => ['#[Native] class User {} function foo(#[StdVector(User::class)] $vec): void {}', 'cannot hold Native objects'];
        yield 'array native elements' => ['#[Native] class User {} function foo(#[StdArray(User::class, 2)] $value): void {}', 'cannot hold Native objects'];
        yield 'reference capture' => ['function foo(#[StdVector(Type::Int)] $vec): void { $fn = function() use (&$vec) {}; }', 'cannot be captured by reference'];
        yield 'unset binding' => ['function foo(#[StdVector(Type::Int)] $vec): void { unset($vec); }', 'bindings cannot be unset'];
        yield 'replace boxed binding' => ['function foo(#[StdVector(Type::Int)] $vec, $other): void { $vec = $other; }', 'bindings cannot be replaced'];
        yield 'incompatible override' => ['class A { public function foo(#[StdVector(Type::Int)] $vec): void {} } class B extends A { public function foo(#[StdVector(Type::Float)] $vec): void {} }', 'must be compatible'];
        yield 'conflicting abstract traits' => ['trait A { abstract public function foo(#[StdVector(Type::Int)] $vec): void; } trait B { abstract public function foo(#[StdVector(Type::Float)] $vec): void; } abstract class C { use A, B; }', 'incompatible types'];
    }
}
