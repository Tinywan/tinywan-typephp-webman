<?php

namespace TypePhp\Tests\NativeClass;

use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TypePhp\Transform\NativeClassAttributeLowering;

final class NativeClassAttributeLoweringTest extends TestCase
{
    public function testConsumesNativeAttributeAndMarksNamedClass(): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $nodes = $parser->parse('<?php #[Native] class Point {}');
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver(null, ['replaceNodes' => false]));
        $traverser->addVisitor(new NativeClassAttributeLowering());
        $nodes = $traverser->traverse($nodes);

        $this->assertInstanceOf(Class_::class, $nodes[0]);
        $this->assertTrue(NativeClassAttributeLowering::isNative($nodes[0]));
        $this->assertSame([], $nodes[0]->attrGroups);
    }

    public function testLeavesOrdinaryClassUnmarked(): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $nodes = $parser->parse('<?php class Point {}');
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NativeClassAttributeLowering());
        $nodes = $traverser->traverse($nodes);

        $this->assertFalse(NativeClassAttributeLowering::isNative($nodes[0]));
    }
}
