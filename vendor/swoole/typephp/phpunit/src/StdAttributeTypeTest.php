<?php

use PHPUnit\Framework\Attributes\DataProvider;
use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;
use TypePhp\Type;

final class StdAttributeTypeTest extends BaseTest
{
    private function translate(string $source): array
    {
        global $translator;
        $directory = sys_get_temp_dir() . '/std-attribute-type-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $file = $directory . '/declarations.php';
        file_put_contents($file, '<?php ' . $source);
        try {
            $translator = CompilerTest::create(TYPEPHP_ROOT_PATH);
            $translator->addFiles([$file]);
            $translator->prepareFile($file);
            return [file_get_contents($translator->convertFile($file)), $translator];
        } finally {
            unlink($file);
            rmdir($directory);
        }
    }

    public function testMatchingParameterStorageTypes(): void
    {
        [$code, $compiler] = $this->translate(<<<'PHP'
function vector_arg(#[StdVector(Type::Int)] box $v): void { $v[] = 1; }
function array_arg(#[StdArray(Type::Int, [2, 3])] box $v): void { $v[1][2] = 1; }
function array_arg_scalar(#[StdArray(Type::Int, 3)] box $v): void { $v[2] = 1; }
function array_arg_vector(#[StdArray(Type::Int, [3])] box $v): void { $v[2] = 1; }
function map_arg(#[StdMap(Type::Str, Type::Int)] box $v): void { $v['a'] = 2; }
function ordered_arg(#[StdOrderedMap(Type::Int, Type::Str)] box $v): void { $v[0] = 'a'; }
function list_arg(#[StdList(Type::Int)] array $v): void { $v[] = 3; }
function dict_arg(#[StdDict(Type::Str, Type::Int)] array &$v): void { $v['a'] = 4; }
PHP);
        self::assertStringContainsString('php_vector_arg(php::Var v)', $code);
        self::assertStringContainsString('php_array_arg(php::Var v)', $code);
        self::assertStringContainsString('php::StdArray<php::StdArray<php::Int, 3>, 2>', $code);
        self::assertStringContainsString('php_list_arg(php::Array v)', $code);
        self::assertStringContainsString('php_dict_arg(php::Array & v)', $code);
        $getFunction = new ReflectionMethod($compiler, 'getFunction');
        self::assertSame(Type::VAR, $getFunction->invoke($compiler, 'vector_arg')->argInfoList[0]->type);
        self::assertSame(
            $getFunction->invoke($compiler, 'array_arg_scalar')->argInfoList[0]->stdContainer,
            $getFunction->invoke($compiler, 'array_arg_vector')->argInfoList[0]->stdContainer,
        );
        self::assertSame(Type::ARRAY, $getFunction->invoke($compiler, 'list_arg')->argInfoList[0]->type);
        self::assertSame(Type::ARRAY_REF, $getFunction->invoke($compiler, 'dict_arg')->argInfoList[0]->type);
    }

    public function testPropertyAnnotationsAndSparseWrites(): void
    {
        [$code, $compiler] = $this->translate(<<<'PHP'
class User { public int $id = 1; }
class State {
    #[StdList(Type::Int)] public array $values = [];
    #[StdDict(Type::Str, User::class)] public array $users = [];
    #[StdList(Type::Str)] public $inferred = [];
    #[StdDict(Type::Int, Type::Str)] public static array $labels = [];
    #[StdVector(Type::Int)] public box $vector;
    #[StdArray(Type::Int, [2, 3])] public box $matrix;
    #[StdMap(Type::Str, Type::Int)] public box $map;
    #[StdOrderedMap(Type::Int, User::class)] public $ordered;
}
#[Native] class NativeState { #[StdList(Type::Int)] public $values = []; }
function write_values(State $s, NativeState $n, $key): void {
    $s->values[-2] = 10;
    $s->values[100] = 20;
    $s->values[] = 30;
    $s->values[$key] = 40;
    $s->users['123'] = new User();
    $s->inferred[100] = 'sparse';
    State::$labels[-3] = 'negative';
    $n->values[-5] = 7;
}
PHP);
        self::assertStringNotContainsString('safeArrayIndex(', $code);
        self::assertStringContainsString('php::toIntExact(', $code);
        $class = (new ReflectionMethod($compiler, 'getClass'))->invoke($compiler, 'State');
        self::assertSame(Type::ARRAY, $class->getProperty('inferred')->type);
        self::assertSame('list', $class->getProperty('values')->typedArray['kind']);
        self::assertSame('dict', $class->getProperty('users')->typedArray['kind']);
        self::assertSame('User', $class->getProperty('users')->typedArray['class']);
        self::assertSame('vector', $class->getProperty('vector')->stdContainer['kind']);
        self::assertSame('array', $class->getProperty('matrix')->stdContainer['kind']);
        self::assertSame([2, 3], $class->getProperty('matrix')->stdContainer['dimensions']);
        self::assertSame('ordered_map', $class->getProperty('ordered')->stdContainer['kind']);
        self::assertSame(Type::BOX, $class->getProperty('ordered')->type);
    }

    #[DataProvider('conflictingDeclarations')]
    public function testConflictingDeclarations(string $source, string $message): void
    {
        try {
            $this->translate($source);
            self::fail('Conflicting container declaration was accepted');
        } catch (TestError|\TypePhp\Exception\SyntaxError $error) {
            self::assertStringContainsString($message, $error->getMessage());
        }
    }

    public static function conflictingDeclarations(): iterable
    {
        foreach (['StdArray(Type::Int, 3)', 'StdVector(Type::Int)', 'StdMap(Type::Str, Type::Int)', 'StdOrderedMap(Type::Int, Type::Str)'] as $attribute) {
            foreach (['array', 'mixed', 'any', '?box', 'box|int'] as $type) {
                yield $attribute . ' parameter ' . $type => ["function f(#[{$attribute}] {$type} \$v): void {}", 'unless it is box'];
                yield $attribute . ' property ' . $type => ["class A { #[{$attribute}] public {$type} \$v; }", 'unless it is box'];
            }
        }
        foreach (['StdList(Type::Int)', 'StdDict(Type::Str, Type::Int)'] as $attribute) {
            foreach (['box', 'mixed', 'any', '?array', 'array|int'] as $type) {
                yield $attribute . ' parameter ' . $type => ["function f(#[{$attribute}] {$type} \$v): void {}", 'unless it is array'];
                yield $attribute . ' property ' . $type => ["class A { #[{$attribute}] public {$type} \$v; }", 'unless it is array'];
            }
        }
        yield 'property container conflict' => ['class A { #[StdVector(Type::Int), StdList(Type::Int)] public $v; }', 'cannot be applied to the same declaration'];
        yield 'property wrong list key' => ['class A { #[StdList(Type::Int)] public array $v = []; } function f(A $a): void { $a->v["1"] = 1; }', 'Typed array key must have type'];
        yield 'property wrong list value' => ['class A { #[StdList(Type::Int)] public array $v = []; } function f(A $a): void { $a->v[] = "1"; }', 'Typed array value must have type'];
        yield 'property dict append' => ['class A { #[StdDict(Type::Int, Type::Int)] public array $v = []; } function f(A $a): void { $a->v[] = 1; }', 'StdDict properties do not support append'];
    }
}
