<?php

namespace TypePhp\Tests\Entity;

use PHPUnit\Framework\TestCase;
use TypePhp\Entity\FunctionDef;
use TypePhp\Entity\ArgInfo;

class FunctionDefExtendedTest extends TestCase
{
    public function testStubFlagDefaultsToFalse(): void
    {
        $fn = new FunctionDef('test', 'php::Int', '');
        $this->assertFalse($fn->stub);
    }

    public function testStubFlagCanBeSet(): void
    {
        $fn = new FunctionDef('test', 'php::Int', '');
        $fn->stub = true;
        $this->assertTrue($fn->stub);
    }

    public function testArgCountRequiredDefault(): void
    {
        $fn = new FunctionDef('test', 'php::Int', '');
        $this->assertSame(0, $fn->argCountRequired);
    }

    public function testArgCountRequiredCanBeSet(): void
    {
        $fn = new FunctionDef('test', 'php::Int', '');
        $fn->argCountRequired = 3;
        $this->assertSame(3, $fn->argCountRequired);
    }

    public function testParamsDefault(): void
    {
        $fn = new FunctionDef('test', 'php::Int', '');
        $this->assertEquals('', $fn->params);
    }

    public function testParamsCanBeSet(): void
    {
        $fn = new FunctionDef('test', 'php::Int', '');
        $fn->params = 'php::Str a, php::Int b';
        $this->assertEquals('php::Str a, php::Int b', $fn->params);
    }

    public function testReturnClassDefault(): void
    {
        $fn = new FunctionDef('test', 'php::Object', '');
        $this->assertEquals('', $fn->returnClass);
    }

    public function testReturnClassCanBeSet(): void
    {
        $fn = new FunctionDef('create', 'php::Object', 'App\\Factory');
        $fn->returnClass = 'App\\Entity\\Product';
        $this->assertEquals('App\\Entity\\Product', $fn->returnClass);
    }

    public function testHasVariadicArgWithMultipleNonVariadicArgs(): void
    {
        $fn = new FunctionDef('test', 'void', '');
        $fn->argInfoList = [
            new ArgInfo('a', 'php::Int'),
            new ArgInfo('b', 'php::Str'),
            new ArgInfo('c', 'php::Float'),
        ];
        $this->assertFalse($fn->hasVariadicArg());
    }

    public function testHasVariadicArgWithSingleNonVariadicArg(): void
    {
        $fn = new FunctionDef('test', 'void', '');
        $fn->argInfoList = [
            new ArgInfo('a', 'php::Int'),
        ];
        $this->assertFalse($fn->hasVariadicArg());
    }

    public function testGetNamespacedNameWithoutNamespaceWithParams(): void
    {
        $fn = new FunctionDef('run', 'void', '');
        $fn->argCountRequired = 1;
        $fn->params = 'php::Int n';
        $this->assertEquals('run', $fn->getNamespacedName());
    }

    public function testMethodFlagWithFullSetup(): void
    {
        $fn = new FunctionDef('handle', 'php::Var', 'App\\Controller');
        $fn->method = true;
        $fn->argInfoList = [
            new ArgInfo('request', 'php::Object'),
        ];
        $fn->returnClass = 'App\\Entity\\Response';

        $this->assertTrue($fn->method);
        $this->assertEquals('App\\Controller\\handle', $fn->getNamespacedName());
        $this->assertEquals('App\\Entity\\Response', $fn->returnClass);
    }
}
