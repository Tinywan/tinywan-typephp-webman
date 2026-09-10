<?php

namespace TypePhpTest\Transform;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypePhp\Transform\NanoSyntaxValidationVisitor;

final class NanoSyntaxValidationVisitorTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function unsupportedSyntax(): iterable
    {
        yield 'eval' => ['<?php function run(): void { eval("return 1;"); }', '`eval`'];
        yield 'include' => ['<?php function run(): void { include "a.php"; }', '`include`'];
        yield 'include once' => ['<?php function run(): void { include_once "a.php"; }', '`include_once`'];
        yield 'require' => ['<?php function run(): void { require "a.php"; }', '`require`'];
        yield 'require once' => ['<?php function run(): void { require_once "a.php"; }', '`require_once`'];
        yield 'backticks' => ['<?php function run(): string { return `uname`; }', 'Backtick shell execution'];
        yield 'anonymous class' => ['<?php function run(): object { return new class {}; }', 'Anonymous classes'];
        yield 'yield' => ['<?php function values(): iterable { yield 1; }', 'Fiber and Generator'];
        yield 'yield from' => ['<?php function values(): iterable { yield from [1]; }', 'Fiber and Generator'];
        yield 'generator closure' => [
            '<?php function values(): Closure { return function (): iterable { yield 1; }; }',
            'Fiber and Generator',
        ];
        yield 'generator arrow function' => [
            '<?php function values(): Closure { return fn (): iterable => yield 1; }',
            'Fiber and Generator',
        ];
        yield 'Fiber class' => [
            '<?php function run(): object { return new Fiber(static function (): void {}); }',
            'Class `Fiber` is not supported',
        ];
        yield 'Generator type' => [
            '<?php function values(): Generator { throw new RuntimeException(); }',
            'Class `Generator` is not supported',
        ];
        yield 'ReflectionGenerator class' => [
            '<?php function inspect(object $value): object { return new ReflectionGenerator($value); }',
            'Class `ReflectionGenerator` is not supported',
        ];
    }

    #[DataProvider('unsupportedSyntax')]
    public function testRejectsRuntimeCompiledSyntax(string $source, string $expected): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $nodes = $parser->parse($source);
        self::assertNotNull($nodes);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver(null, ['replaceNodes' => false]));
        $traverser->addVisitor(new NanoSyntaxValidationVisitor(
            static function (Node $node, string $message): never {
                throw new RuntimeException($message . ':' . $node->getStartLine());
            },
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expected);
        $traverser->traverse($nodes);
    }

    public function testAcceptsNamedClassesAndOrdinaryObjectConstruction(): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $nodes = $parser->parse(
            '<?php class Value {} function run(): object { return new Value(); }',
        );
        self::assertNotNull($nodes);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver(null, ['replaceNodes' => false]));
        $traverser->addVisitor(new NanoSyntaxValidationVisitor(
            static function (Node $node, string $message): never {
                throw new RuntimeException($message . ':' . $node->getStartLine());
            },
        ));

        self::assertCount(2, $traverser->traverse($nodes));
    }

    public function testAcceptsNamespacedUserClassNamedFiber(): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $nodes = $parser->parse(
            '<?php namespace App; class Fiber {} function run(): Fiber { return new Fiber(); }',
        );
        self::assertNotNull($nodes);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver(null, ['replaceNodes' => false]));
        $traverser->addVisitor(new NanoSyntaxValidationVisitor(
            static function (Node $node, string $message): never {
                throw new RuntimeException($message . ':' . $node->getStartLine());
            },
        ));

        self::assertCount(1, $traverser->traverse($nodes));
    }

    public function testFullRuntimeNanoPolicyKeepsGeneratorsButRejectsVmEntrySyntax(): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $accepted = $parser->parse(
            '<?php function values(): Generator { yield 1; }',
        );
        self::assertNotNull($accepted);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver(null, ['replaceNodes' => false]));
        $traverser->addVisitor(new NanoSyntaxValidationVisitor(
            static function (Node $node, string $message): never {
                throw new RuntimeException($message . ':' . $node->getStartLine());
            },
            false,
        ));
        self::assertCount(1, $traverser->traverse($accepted));

        foreach ([
            '<?php eval("return 1;");',
            '<?php require "a.php";',
            '<?php `uname`;',
            '<?php $value = new class {};',
        ] as $source) {
            $nodes = $parser->parse($source);
            self::assertNotNull($nodes);
            try {
                $traverser->traverse($nodes);
                self::fail("Nano policy accepted unsupported source: {$source}");
            } catch (RuntimeException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
