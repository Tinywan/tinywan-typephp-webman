<?php

namespace TypePhp\Tests\Entity;

use PHPUnit\Framework\TestCase;
use TypePhp\Entity\FunctionDef;
use TypePhp\Entity\ArgInfo;

class FunctionDefTest extends TestCase
{
    public function testConstruct(): void
    {
        $fn = new FunctionDef('test', 'void', '');

        $this->assertEquals('test', $fn->name);
        $this->assertEquals('void', $fn->returnType);
        $this->assertEquals('', $fn->namespace);
        $this->assertFalse($fn->method);
        $this->assertFalse($fn->stub);
        $this->assertFalse($fn->hot);
        $this->assertFalse($fn->cold);
        $this->assertEmpty($fn->argInfoList);
        $this->assertEquals(0, $fn->argCountRequired);
        $this->assertEquals('', $fn->params);
        $this->assertEquals('', $fn->returnClass);
    }

    public function testConstructWithNamespace(): void
    {
        $fn = new FunctionDef('run', 'php::Int', 'App\\Lib');

        $this->assertEquals('run', $fn->name);
        $this->assertEquals('php::Int', $fn->returnType);
        $this->assertEquals('App\\Lib', $fn->namespace);
    }

    public function testGetNamespacedNameWithoutNamespace(): void
    {
        $fn = new FunctionDef('main', 'void', '');
        $this->assertEquals('main', $fn->getNamespacedName());
    }

    public function testGetNamespacedNameWithNamespace(): void
    {
        $fn = new FunctionDef('run', 'void', 'App\\Lib');
        $this->assertEquals('App\\Lib\\run', $fn->getNamespacedName());
    }

    public function testHasVariadicArgEmptyList(): void
    {
        $fn = new FunctionDef('test', 'void', '');
        $this->assertFalse($fn->hasVariadicArg());
    }

    public function testHasVariadicArgFalse(): void
    {
        $fn = new FunctionDef('test', 'void', '');

        $arg1 = new ArgInfo();
        $arg1->name = 'x';
        $arg1->type = 'php::Int';
        $arg1->variadic = false;

        $fn->argInfoList = [$arg1];
        $this->assertFalse($fn->hasVariadicArg());
    }

    public function testHasVariadicArgTrue(): void
    {
        $fn = new FunctionDef('test', 'void', '');

        $arg1 = new ArgInfo();
        $arg1->name = 'args';
        $arg1->type = 'php::Var';
        $arg1->variadic = true;

        $fn->argInfoList = [$arg1];
        $this->assertTrue($fn->hasVariadicArg());
    }

    public function testHasVariadicArgLastOnly(): void
    {
        $fn = new FunctionDef('test', 'void', '');

        $arg1 = new ArgInfo();
        $arg1->name = 'x';
        $arg1->type = 'php::Int';
        $arg1->variadic = false;

        $arg2 = new ArgInfo();
        $arg2->name = 'rest';
        $arg2->type = 'php::Var';
        $arg2->variadic = true;

        $fn->argInfoList = [$arg1, $arg2];
        $this->assertTrue($fn->hasVariadicArg());
    }

    public function testMethodFlag(): void
    {
        $fn = new FunctionDef('handle', 'void', '');
        $this->assertFalse($fn->method);

        $fn->method = true;
        $this->assertTrue($fn->method);
    }

    public function testParamsAndReturnClassSettable(): void
    {
        $fn = new FunctionDef('test', 'php::Object', '');
        $fn->params = 'php::Int x, php::Str y';
        $fn->returnClass = 'DateTime';

        $this->assertEquals('php::Int x, php::Str y', $fn->params);
        $this->assertEquals('DateTime', $fn->returnClass);
    }
}
