<?php

namespace TypePhp\Tests\Analysis;

use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use TypePhp\Analysis\ReferenceCaptureAnalyzer;

final class ReferenceCaptureAnalyzerTest extends TestCase
{
    public function testCollectsOnlyCapturesOwnedByTheCurrentFunctionScope(): void
    {
        $nodes = (new ParserFactory())->createForHostVersion()->parse(<<<'PHP'
<?php
function example(): void {
    static $staticValue = '';
    global $globalValue;
    $closure = function () use (&$outer, $copy): void {
        $nested = function () use (&$inner): void {};
    };
}
PHP);
        self::assertNotNull($nodes);

        $finder = new NodeFinder();
        $function = $finder->findFirstInstanceOf($nodes, Function_::class);
        self::assertInstanceOf(Function_::class, $function);

        $analysis = (new ReferenceCaptureAnalyzer())->analyze($function->stmts);
        self::assertSame(['outer' => true], $analysis['captures']);
        self::assertSame(
            ['staticValue' => true, 'globalValue' => true],
            $analysis['nonLocals'],
        );

        $closure = $finder->findFirstInstanceOf($function->stmts, Closure::class);
        self::assertInstanceOf(Closure::class, $closure);
        $nestedAnalysis = (new ReferenceCaptureAnalyzer())->analyze($closure->stmts);
        self::assertSame(['inner' => true], $nestedAnalysis['captures']);
    }
}
