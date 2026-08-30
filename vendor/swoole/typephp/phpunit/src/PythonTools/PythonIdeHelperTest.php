<?php

namespace TypePhpTest\PythonTools;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use TypePhp\PythonTools\IdeHelper\HelperRenderer;
use TypePhp\PythonTools\IdeHelper\PhpyModuleScanner;
use TypePhp\PythonTools\IdeHelper\PyObjectHelperRenderer;

final class PythonIdeHelperTest extends TestCase
{
    public function testRendererProducesInertNamespaceHelper(): void
    {
        $metadata = [
            'module' => 'demo.widgets',
            'doc' => 'Demo module.',
            'attributes' => [
                ['name' => 'VERSION'],
                ['name' => 'class'],
            ],
            'functions' => [
                [
                    'name' => 'create',
                    'parameters' => [
                        ['name' => 'value', 'optional' => false, 'variadic' => false],
                        ['name' => 'mode', 'optional' => true, 'variadic' => false],
                    ],
                ],
            ],
            'classes' => [
                [
                    'name' => 'Widget',
                    'parameters' => [],
                    'methods' => [
                        ['name' => 'render', 'parameters' => []],
                    ],
                    'properties' => ['name'],
                ],
            ],
        ];

        $helper = (new HelperRenderer())->render($metadata);

        self::assertStringContainsString('@generated TypePHP Python IDE helper', $helper);
        self::assertStringContainsString('namespace python\\demo\\widgets;', $helper);
        self::assertStringNotContainsString('if (false)', $helper);
        self::assertStringEndsWith("die(\\PyObject::IDE_HELPER_ONLY);\n", $helper);
        self::assertStringContainsString('const VERSION = new \\PyObject();', $helper);
        self::assertStringContainsString("\nconst VERSION = new \\PyObject();", $helper);
        self::assertStringNotContainsString('/** @var \\PyObject */', $helper);
        self::assertStringContainsString("\nclass Widget extends \\PyObject", $helper);
        self::assertStringContainsString("\n    public function render()", $helper);
        self::assertStringNotContainsString('const class =', $helper);
        self::assertStringNotContainsString('define(', $helper);
        self::assertStringContainsString(
            'function create(mixed $value, mixed $mode = null): \\PyObject { die(\\PyObject::IDE_HELPER_ONLY); }',
            $helper,
        );
        self::assertStringContainsString('class Widget extends \\PyObject', $helper);
        self::assertStringContainsString('function Widget(): Widget { die(\\PyObject::IDE_HELPER_ONLY); }', $helper);
        self::assertStringContainsString('public function __construct() { parent::__construct(); }', $helper);
        self::assertStringContainsString('public function render(): \\PyObject { die(\\PyObject::IDE_HELPER_ONLY); }', $helper);
        self::assertStringNotContainsString('PyCore::import', $helper);
    }

    public function testBuiltinsAreRenderedInPythonRootNamespace(): void
    {
        $helper = (new HelperRenderer())->render([
            'module' => 'builtins',
            'doc' => '',
            'attributes' => [],
            'functions' => [['name' => 'len', 'parameters' => []]],
            'classes' => [],
        ]);

        self::assertStringContainsString('namespace python;', $helper);
        self::assertStringContainsString('function len(): \\PyObject', $helper);
    }

    public function testRendererProducesAnInertPyObjectHelper(): void
    {
        $helper = (new PyObjectHelperRenderer())->render();

        self::assertStringNotContainsString('if (false)', $helper);
        self::assertStringEndsWith("die(PyObject::IDE_HELPER_ONLY);\n", $helper);
        self::assertStringContainsString(
            'class PyObject implements \\ArrayAccess, \\Iterator, \\Countable',
            $helper,
        );
        self::assertStringContainsString("\nclass PyObject implements", $helper);
        self::assertStringContainsString("\n    public const IDE_HELPER_ONLY", $helper);
        self::assertStringContainsString("public const IDE_HELPER_ONLY = 'IDE helper only';", $helper);
        self::assertStringNotContainsString('enum PyObjectConstant', $helper);
        self::assertStringContainsString('public function toArray(): array { die(self::IDE_HELPER_ONLY); }', $helper);
        self::assertStringContainsString('public function toValue(): mixed { die(self::IDE_HELPER_ONLY); }', $helper);
        self::assertStringContainsString('public function __toString(): string { die(self::IDE_HELPER_ONLY); }', $helper);
        self::assertStringContainsString('public function next(): void {}', $helper);
        self::assertStringContainsString('TypePHP keyword methods are compiler intrinsics', $helper);
        self::assertStringContainsString('public function toInt(): int { die(self::IDE_HELPER_ONLY); }', $helper);
        self::assertStringContainsString('public function toFloat(): float { die(self::IDE_HELPER_ONLY); }', $helper);
        self::assertStringContainsString('public function toString(): string { die(self::IDE_HELPER_ONLY); }', $helper);
        self::assertStringContainsString('public function toBool(): bool { die(self::IDE_HELPER_ONLY); }', $helper);
        self::assertStringContainsString('public function toStream(): mixed { die(self::IDE_HELPER_ONLY); }', $helper);
        self::assertStringContainsString('public function toBigInt(): mixed { die(self::IDE_HELPER_ONLY); }', $helper);
        self::assertStringContainsString('public function toBigFloat(): mixed { die(self::IDE_HELPER_ONLY); }', $helper);
        self::assertStringContainsString('public function toDecimal(): mixed { die(self::IDE_HELPER_ONLY); }', $helper);
        self::assertStringContainsString('public function toObject(?string $class = null): object', $helper);
        self::assertStringContainsString('public function toAny(): mixed { die(self::IDE_HELPER_ONLY); }', $helper);
        self::assertStringContainsString('public function toRef(): mixed { die(self::IDE_HELPER_ONLY); }', $helper);
        self::assertSame(1, substr_count($helper, 'public function toArray(): array'));
    }

    public function testRendererOmitsPythonCountMethodInheritedFromPyObject(): void
    {
        $helper = (new HelperRenderer())->render([
            'module' => 'demo',
            'attributes' => [],
            'functions' => [],
            'classes' => [[
                'name' => 'Container',
                'parameters' => [],
                'properties' => [],
                'methods' => [
                    ['name' => 'count', 'parameters' => [['name' => 'value']]],
                    ['name' => 'append', 'parameters' => [['name' => 'value']]],
                ],
            ]],
        ]);

        self::assertStringNotContainsString('public function count(', $helper);
        self::assertStringContainsString('public function append(mixed $value): \\PyObject', $helper);
    }

    #[RequiresPhpExtension('phpy')]
    public function testPhpyScannerReadsRealPythonModule(): void
    {
        $metadata = (new PhpyModuleScanner())->scan('math');
        $functions = array_column($metadata['functions'], null, 'name');
        $attributes = array_column($metadata['attributes'], null, 'name');

        self::assertArrayHasKey('sqrt', $functions);
        self::assertSame('x', $functions['sqrt']['parameters'][0]['name']);
        self::assertArrayHasKey('pi', $attributes);
    }

    #[RequiresPhpExtension('phpy')]
    public function testPhpyScannerReadsClassesWithoutRetainingDynamicCallTrampolines(): void
    {
        $metadata = (new PhpyModuleScanner())->scan('json');
        $classes = array_column($metadata['classes'], null, 'name');

        self::assertArrayHasKey('JSONDecoder', $classes);
        self::assertNotEmpty($classes['JSONDecoder']['methods']);
    }
}
