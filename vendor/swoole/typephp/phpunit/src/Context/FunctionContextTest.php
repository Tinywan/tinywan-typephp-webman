<?php

namespace TypePhp\Tests\Context;

use PHPUnit\Framework\TestCase;
use TypePhp\Context\FunctionContext;
use TypePhp\Context\ScopeContext;

class FunctionContextTest extends TestCase
{
    public function testConstructInitializesAllProperties(): void
    {
        $ctx = new FunctionContext();

        $this->assertEmpty($ctx->objects);
        $this->assertEmpty($ctx->stdArrays);
        $this->assertEmpty($ctx->stdContainers);
        $this->assertEmpty($ctx->localVars);
        $this->assertEmpty($ctx->staticVars);
        $this->assertEmpty($ctx->globalVars);
        $this->assertEmpty($ctx->ceWrappers);
        $this->assertEmpty($ctx->arguments);
        $this->assertEmpty($ctx->beforeStmtLines);
        $this->assertEmpty($ctx->afterStmtLines);
        $this->assertEmpty($ctx->objectProps);
        $this->assertEmpty($ctx->scopeLayouts);

        $this->assertSame(0, $ctx->tmpVarIndex);
        $this->assertSame(0, $ctx->scopeLevel);
        $this->assertFalse($ctx->inLoop);
        $this->assertFalse($ctx->inClosure);
        $this->assertFalse($ctx->needsUserCodeCallableScope);
    }

    public function testEnterScopeIncrementsLevel(): void
    {
        $ctx = new FunctionContext();
        $this->assertSame(0, $ctx->scopeLevel);

        $ctx->enterScope();
        $this->assertSame(1, $ctx->scopeLevel);
        $this->assertArrayHasKey(0, $ctx->scopeLayouts);
        $this->assertInstanceOf(ScopeContext::class, $ctx->scopeLayouts[0]);

        $ctx->enterScope();
        $this->assertSame(2, $ctx->scopeLevel);
        $this->assertArrayHasKey(0, $ctx->scopeLayouts);
        $this->assertArrayHasKey(1, $ctx->scopeLayouts);
    }

    public function testLeaveScopeDecrementsLevel(): void
    {
        $ctx = new FunctionContext();
        $ctx->enterScope();
        $ctx->enterScope();
        $this->assertSame(2, $ctx->scopeLevel);

        $ctx->leaveScope();
        $this->assertSame(1, $ctx->scopeLevel);

        $ctx->leaveScope();
        $this->assertSame(0, $ctx->scopeLevel);
    }

    public function testMutableProperties(): void
    {
        $ctx = new FunctionContext();

        $ctx->inLoop = true;
        $this->assertTrue($ctx->inLoop);

        $ctx->inClosure = true;
        $this->assertTrue($ctx->inClosure);

        $ctx->tmpVarIndex = 5;
        $this->assertSame(5, $ctx->tmpVarIndex);

        $ctx->needsUserCodeCallableScope = true;
        $this->assertTrue($ctx->needsUserCodeCallableScope);
    }

    public function testLocalVarsManipulation(): void
    {
        $ctx = new FunctionContext();
        $ctx->localVars['x'] = 'php::Int';
        $ctx->localVars['name'] = 'php::Str';

        $this->assertArrayHasKey('x', $ctx->localVars);
        $this->assertArrayHasKey('name', $ctx->localVars);
        $this->assertEquals('php::Int', $ctx->localVars['x']);
        $this->assertEquals('php::Str', $ctx->localVars['name']);
    }

    public function testObjectsMap(): void
    {
        $ctx = new FunctionContext();
        $ctx->objects['obj1'] = 'stdClass';
        $ctx->objects['obj2'] = 'DateTime';

        $this->assertCount(2, $ctx->objects);
        $this->assertEquals('stdClass', $ctx->objects['obj1']);
    }

    public function testBeforeAfterStmtLines(): void
    {
        $ctx = new FunctionContext();
        $ctx->beforeStmtLines[] = 'int x = 1;';
        $ctx->afterStmtLines[] = 'x = 0;';

        $this->assertCount(1, $ctx->beforeStmtLines);
        $this->assertCount(1, $ctx->afterStmtLines);
    }
}
