<?php

namespace TypePhp\Tests\Analysis;

use PHPUnit\Framework\TestCase;
use TypePhp\Analysis\SsaBlock;
use TypePhp\Analysis\SsaBuilder;
use TypePhp\Analysis\SsaFlags;
use TypePhp\Analysis\SsaVar;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;

class SsaBuilderTest extends TestCase
{
    private function parsePhp(string $code): array
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $stmts = $parser->parse('<?php ' . $code);
        $this->assertNotNull($stmts);
        return $stmts;
    }

    private function getFunctionStmts(string $code): array
    {
        $stmts = $this->parsePhp($code);
        $fn = $stmts[0];
        if ($fn instanceof Stmt\Expression) {
            // For standalone expressions, wrap in a function-like context
            return $stmts;
        }
        $this->assertInstanceOf(FunctionLike::class, $fn);
        return $fn->getStmts() ?: [];
    }

    private function buildSsa(string $code): SsaBuilder
    {
        $stmts = $this->parsePhp('function f($x) { ' . $code . ' }');
        $fn = $stmts[0];
        $params = [];
        foreach ($fn->getParams() as $param) {
            $params[] = (object)[
                'name' => $param->var->name,
                'byRef' => $param->byRef ? 1 : 0,
                'variadic' => $param->variadic,
            ];
        }
        $builder = new SsaBuilder($fn->getStmts() ?: [], $params);
        $builder->build();
        return $builder;
    }

    // ========================================================================
    // Basic block construction
    // ========================================================================

    public function testSingleAssignmentSingleBlock(): void
    {
        $builder = $this->buildSsa('$x = 1;');
        $this->assertCount(2, $builder->blocks); // entry + exit
        $this->assertEquals(1, count($builder->blocks[0]->stmts));
        $this->assertCount(0, $builder->blocks[0]->predecessors);
        $this->assertContains($builder->blocks[count($builder->blocks) - 1]->id, $builder->blocks[0]->successors);
    }

    public function testMultipleStatementsSingleBlock(): void
    {
        $builder = $this->buildSsa('$a = 1; $b = 2; $c = $a + $b;');
        $this->assertCount(2, $builder->blocks);
        $this->assertCount(3, $builder->blocks[0]->stmts);
    }

    public function testReturnSplitsBlock(): void
    {
        $builder = $this->buildSsa('$a = 1; return $a; $b = 2;');
        // Should have 3 blocks: entry (a=1, return), after return (b=2), exit
        $this->assertGreaterThanOrEqual(3, count($builder->blocks));
    }

    // ========================================================================
    // Goto and label handling
    // ========================================================================

    public function testGotoSplitsBlocks(): void
    {
        $builder = $this->buildSsa('
            goto end;
            $a = 1;
            end:
            $b = 2;
        ');

        $this->assertGreaterThanOrEqual(3, count($builder->blocks));

        // Find the goto block
        $gotoBlock = null;
        $labelBlock = null;
        foreach ($builder->blocks as $block) {
            if ($block->endsWithGoto && $block->gotoLabel === 'end') {
                $gotoBlock = $block;
            }
            if ($block->isGotoTarget && $block->labelName === 'end') {
                $labelBlock = $block;
            }
        }
        $this->assertNotNull($gotoBlock, 'Goto block not found');
        $this->assertNotNull($labelBlock, 'Label block not found');
        $this->assertTrue($gotoBlock->endsWithGoto);
        $this->assertContains($labelBlock->id, $gotoBlock->successors);
        $this->assertContains($gotoBlock->id, $labelBlock->predecessors);
    }

    public function testLabelBlockMapping(): void
    {
        $builder = $this->buildSsa('
            goto skip;
            skip:
            $x = 1;
        ');
        $labelBlockId = $builder->getLabelBlock('skip');
        $this->assertNotNull($labelBlockId);
        $this->assertTrue($builder->blocks[$labelBlockId]->isGotoTarget);
    }

    // ========================================================================
    // SSA variable renaming
    // ========================================================================

    public function testParameterCreatesSsaVar(): void
    {
        $builder = $this->buildSsa('$y = $x;');
        $this->assertNotEmpty($builder->ssaVars);

        $paramVar = null;
        foreach ($builder->ssaVars as $var) {
            if ($var->origName === 'x' && ($var->flags & SsaFlags::PARAM)) {
                $paramVar = $var;
                break;
            }
        }
        $this->assertNotNull($paramVar, 'Parameter x should have an SSA var');
    }

    public function testAssignmentCreatesNewSsaVar(): void
    {
        $builder = $this->buildSsa('$x = 1; $x = 2;');
        $xVars = [];
        foreach ($builder->ssaVars as $var) {
            if ($var->origName === 'x' && !($var->flags & SsaFlags::PARAM) && !($var->flags & SsaFlags::PHI)) {
                $xVars[] = $var;
            }
        }
        $this->assertCount(2, $xVars, 'Two assignments to $x should create two SSA vars');
    }

    public function testAssignOpAndIncDecCreateNewSsaVars(): void
    {
        $builder = $this->buildSsa('$y = 1; $y &= 3; $y++; --$y;');
        $yVars = [];
        foreach ($builder->ssaVars as $var) {
            if ($var->origName === 'y' && !($var->flags & SsaFlags::PHI)) {
                $yVars[] = $var;
            }
        }

        $this->assertCount(4, $yVars, 'Assignment, compound assignment, and inc/dec should each define $y');
    }

    public function testVarDefBlocks(): void
    {
        $builder = $this->buildSsa('$a = 1; $b = 2;');
        $aBlocks = $builder->getDefBlocks('a');
        $bBlocks = $builder->getDefBlocks('b');
        $this->assertNotEmpty($aBlocks);
        $this->assertNotEmpty($bBlocks);
    }

    // ========================================================================
    // Branching and φ function placement
    // ========================================================================

    public function testIfBranchCreatesMultipleBlocks(): void
    {
        $builder = $this->buildSsa('
            $x = 1;
            if ($x > 0) {
                $x = 2;
            }
            return $x;
        ');
        // Should have more than just entry + exit
        $this->assertGreaterThan(2, count($builder->blocks));
    }

    public function testDominatorTreeComputed(): void
    {
        $builder = $this->buildSsa('
            $x = 1;
            if ($x > 0) {
                $x = 2;
            } else {
                $x = 3;
            }
            return $x;
        ');
        // Entry block dominates all others
        $entryId = 0;
        foreach ($builder->blocks as $block) {
            if ($block->id === $entryId) continue;
            $this->assertGreaterThanOrEqual(0, $block->dominator, "Block {$block->id} should have a dominator");
        }
    }

    public function testImmediateDominatorUsesClosestDominator(): void
    {
        $builder = $this->buildSsa('
            $a = 1;
            target:
            $b = 2;
        ');

        $exitBlock = $builder->blocks[count($builder->blocks) - 1];
        $labelBlockId = $builder->getLabelBlock('target');

        $this->assertNotNull($labelBlockId);
        $this->assertSame($labelBlockId, $exitBlock->dominator);
    }

    public function testPhiFunctionPlacedAtJoin(): void
    {
        $builder = $this->buildSsa('
            $x = 1;
            if ($x > 0) {
                $x = 2;
            } else {
                $x = 3;
            }
            return $x;
        ');

        // Check for φ function for $x at the join point
        $hasPhi = false;
        foreach ($builder->blocks as $block) {
            if ($builder->hasPhiAtBlock('x', $block->id)) {
                $hasPhi = true;
                break;
            }
        }
        $this->assertTrue($hasPhi, 'φ function should be placed at join point for $x');
    }

    // ========================================================================
    // unset() handling
    // ========================================================================

    public function testUnsetKillsVariable(): void
    {
        $builder = $this->buildSsa('
            $x = 1;
            unset($x);
            $x = 2;
        ');

        $killedVar = null;
        foreach ($builder->ssaVars as $var) {
            if ($var->origName === 'x' && ($var->flags & SsaFlags::KILLED)) {
                $killedVar = $var;
                break;
            }
        }
        $this->assertNotNull($killedVar, 'unset($x) should create a KILLED SSA var');
        $this->assertTrue((bool)($killedVar->flags & SsaFlags::UNDEFINED));
    }

    // ========================================================================
    // Reference handling
    // ========================================================================

    public function testAssignRefCreatesRefSsaVar(): void
    {
        $builder = $this->buildSsa('
            $a = 1;
            $b =& $a;
        ');

        $refVar = null;
        foreach ($builder->ssaVars as $var) {
            if ($var->origName === 'b' && ($var->flags & SsaFlags::REFERENCE)) {
                $refVar = $var;
                break;
            }
        }
        $this->assertNotNull($refVar, 'AssignRef should create REFERENCE SSA var');
    }

    public function testCallByRefCreatesEscapedVar(): void
    {
        $builder = $this->buildSsa('
            foo(&$x);
        ');

        $escapedVar = null;
        foreach ($builder->ssaVars as $var) {
            if ($var->origName === 'x' && ($var->flags & SsaFlags::ESCAPED)) {
                $escapedVar = $var;
                break;
            }
        }
        $this->assertNotNull($escapedVar, 'Call by reference should create ESCAPED SSA var');
    }

    public function testNestedCallByRefCreatesEscapedVar(): void
    {
        $builder = $this->buildSsa('
            $y = some_func(refval($x));
        ');

        $escapedVar = null;
        foreach ($builder->ssaVars as $var) {
            if ($var->origName === 'x' && ($var->flags & SsaFlags::ESCAPED)) {
                $escapedVar = $var;
                break;
            }
        }
        $this->assertNotNull($escapedVar, 'Nested refval($x) should create ESCAPED SSA var');
    }

    public function testCallByRefInsideLoopCreatesEscapedVar(): void
    {
        $builder = $this->buildSsa('
            while ($x) {
                some_func(refval($x));
            }
        ');

        $escapedVar = null;
        foreach ($builder->ssaVars as $var) {
            if ($var->origName === 'x' && ($var->flags & SsaFlags::ESCAPED)) {
                $escapedVar = $var;
                break;
            }
        }
        $this->assertNotNull($escapedVar, 'refval($x) inside loop body should create ESCAPED SSA var');
    }

    public function testRefvalCallByRefCreatesEscapedVar(): void
    {
        // refval() is the AOT compiler's pseudo-function for dynamic call reference passing
        $builder = $this->buildSsa('
            some_func(refval($x));
        ');

        $escapedVar = null;
        foreach ($builder->ssaVars as $var) {
            if ($var->origName === 'x' && ($var->flags & SsaFlags::ESCAPED)) {
                $escapedVar = $var;
                break;
            }
        }
        $this->assertNotNull($escapedVar, 'refval() call should create ESCAPED SSA var for its argument');
    }

    public function testRefvalWithMultipleArgs(): void
    {
        $builder = $this->buildSsa('
            some_func($a, refval($b), refval($c));
        ');

        $escapedB = false;
        $escapedC = false;
        foreach ($builder->ssaVars as $var) {
            if ($var->origName === 'b' && ($var->flags & SsaFlags::ESCAPED)) {
                $escapedB = true;
            }
            if ($var->origName === 'c' && ($var->flags & SsaFlags::ESCAPED)) {
                $escapedC = true;
            }
        }
        $this->assertTrue($escapedB, 'refval($b) should create ESCAPED SSA var');
        $this->assertTrue($escapedC, 'refval($c) should create ESCAPED SSA var');

        // $a is NOT passed by ref — it should NOT be escaped
        $aEscaped = false;
        foreach ($builder->ssaVars as $var) {
            if ($var->origName === 'a' && ($var->flags & SsaFlags::ESCAPED)) {
                $aEscaped = true;
            }
        }
        $this->assertFalse($aEscaped, '$a (not refval) should NOT be escaped');
    }

    // ========================================================================
    // e-SSA pi constraints
    // ========================================================================

    public function testPiConstraintForInstanceof(): void
    {
        $builder = $this->buildSsa('
            if ($x instanceof Foo) {
                $y = $x;
            }
        ');

        // Find the if statement in the first block
        $ifStmt = null;
        foreach ($builder->blocks[0]->stmts as $stmt) {
            if ($stmt instanceof Stmt\If_) {
                $ifStmt = $stmt;
                break;
            }
        }
        $this->assertNotNull($ifStmt, 'If statement should be in first block');

        $result = $builder->buildPiConstraints($ifStmt);
        $this->assertNotEmpty($result['trueVars']);
        $this->assertArrayHasKey('x', $result['trueVars']);
        $this->assertEquals('Foo', $result['trueVars']['x']->narrowedType);
    }

    public function testPiConstraintForIsInt(): void
    {
        $stmts = $this->parsePhp('function f($x) { if (is_int($x)) { return $x; } }');
        $fn = $stmts[0];
        $builder = new SsaBuilder($fn->getStmts() ?: [], []);
        $builder->build();

        $ifStmt = $builder->blocks[0]->stmts[0] ?? null;
        $this->assertInstanceOf(Stmt\If_::class, $ifStmt);

        $result = $builder->buildPiConstraints($ifStmt);
        if (isset($result['trueVars']['x'])) {
            $this->assertEquals('int', $result['trueVars']['x']->narrowedType);
        }
    }

    public function testPiConstraintNegation(): void
    {
        $stmts = $this->parsePhp('function f($x) { if (!$x instanceof Foo) { return; } }');
        $fn = $stmts[0];
        $builder = new SsaBuilder($fn->getStmts() ?: [], []);
        $builder->build();

        $ifStmt = $builder->blocks[0]->stmts[0] ?? null;
        $this->assertInstanceOf(Stmt\If_::class, $ifStmt);

        $result = $builder->buildPiConstraints($ifStmt);
        // Negation flips true/false — the TRUE branch should have NOT Foo
        if (isset($result['trueVars']['x'])) {
            $this->assertStringContainsString('!', $result['trueVars']['x']->narrowedType);
        }
        if (isset($result['falseVars']['x'])) {
            $this->assertStringNotContainsString('!', $result['falseVars']['x']->narrowedType);
        }
    }

    // ========================================================================
    // Dump output
    // ========================================================================

    public function testDumpProducesOutput(): void
    {
        $builder = $this->buildSsa('$x = 1; $y = $x;');
        $dump = $builder->dump();
        $this->assertStringContainsString('SSA Builder Dump', $dump);
        $this->assertStringContainsString('Blocks:', $dump);
        $this->assertStringContainsString('SSA Vars:', $dump);
    }

    // ========================================================================
    // Edge cases
    // ========================================================================

    public function testEmptyFunction(): void
    {
        $builder = $this->buildSsa('');
        $this->assertCount(2, $builder->blocks); // entry + exit
    }

    public function testForeachDefinesVariables(): void
    {
        $builder = $this->buildSsa('
            foreach ($arr as $key => $value) {
                $sum = $sum + $value;
            }
        ');

        $hasKey = false;
        $hasValue = false;
        foreach ($builder->ssaVars as $var) {
            if ($var->origName === 'key') $hasKey = true;
            if ($var->origName === 'value') $hasValue = true;
        }
        $this->assertTrue($hasKey, 'foreach key variable should be defined');
        $this->assertTrue($hasValue, 'foreach value variable should be defined');
    }

    public function testLoopBodyAssignmentsCreateSsaVars(): void
    {
        $builder = $this->buildSsa('
            $obj = new Foo();
            while ($x) {
                $obj = new Foo();
            }
        ');

        $objVars = [];
        foreach ($builder->ssaVars as $var) {
            if ($var->origName === 'obj' && !($var->flags & SsaFlags::PHI)) {
                $objVars[] = $var;
            }
        }

        $this->assertCount(2, $objVars, 'Assignment inside loop body should be tracked as an SSA definition');
        $this->assertContains(0, $builder->getDefBlocks('obj'));
    }

    public function testStaticVariableIsEscaped(): void
    {
        $builder = $this->buildSsa('
            static $count = 0;
            $count++;
        ');

        $staticVar = null;
        foreach ($builder->ssaVars as $var) {
            if ($var->origName === 'count' && ($var->flags & SsaFlags::ESCAPED)) {
                $staticVar = $var;
                break;
            }
        }
        $this->assertNotNull($staticVar, 'static variable should have ESCAPED flag');
    }

    public function testCatchVariable(): void
    {
        $builder = $this->buildSsa('
            try {
                throw new Exception();
            } catch (Exception $e) {
                $msg = $e->getMessage();
            }
        ');

        $hasE = false;
        foreach ($builder->ssaVars as $var) {
            if ($var->origName === 'e') {
                $hasE = true;
                break;
            }
        }
        $this->assertTrue($hasE, 'catch variable should have an SSA var');
    }
}
