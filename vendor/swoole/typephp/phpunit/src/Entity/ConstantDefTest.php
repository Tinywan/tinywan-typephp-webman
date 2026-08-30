<?php

namespace TypePhp\Tests\Entity;

use PHPUnit\Framework\TestCase;
use TypePhp\Entity\ConstantDef;
use PhpParser\Modifiers;

class ConstantDefTest extends TestCase
{
    public function testConstruct(): void
    {
        $const = new ConstantDef('MAX_SIZE', Modifiers::PUBLIC, 'php::Int', '1024L');

        $this->assertEquals('MAX_SIZE', $const->name);
        $this->assertSame(Modifiers::PUBLIC, $const->flags);
        $this->assertEquals('php::Int', $const->type);
        $this->assertEquals('1024L', $const->value);
        $this->assertEquals('', $const->arrayExpr);
        $this->assertEquals('', $const->class);
        $this->assertNull($const->valueExpr);
    }

    public function testConstructWithStringValue(): void
    {
        $const = new ConstantDef('APP_NAME', Modifiers::PUBLIC, 'php::Str', 'php::Str{"MyApp"}');

        $this->assertEquals('APP_NAME', $const->name);
        $this->assertEquals('php::Str', $const->type);
        $this->assertEquals('php::Str{"MyApp"}', $const->value);
    }

    public function testClassProperty(): void
    {
        $const = new ConstantDef('PI', Modifiers::PUBLIC, 'php::Float', '3.14');
        $this->assertEquals('', $const->class);

        $const->class = 'Math';
        $this->assertEquals('Math', $const->class);
    }

    public function testArrayExprProperty(): void
    {
        $const = new ConstantDef('ITEMS', Modifiers::PUBLIC, 'php::Array', '[]');
        $this->assertEquals('', $const->arrayExpr);

        $const->arrayExpr = 'zval arr; array_init(&arr);';
        $this->assertEquals('zval arr; array_init(&arr);', $const->arrayExpr);
    }

    public function testValueExprProperty(): void
    {
        $const = new ConstantDef('VALUE', Modifiers::PUBLIC, 'php::Int', '1L');
        $this->assertNull($const->valueExpr);
    }

    public function testDifferentVisibilityFlags(): void
    {
        $publicConst = new ConstantDef('A', Modifiers::PUBLIC, 'php::Int', '1');
        $this->assertSame(Modifiers::PUBLIC, $publicConst->flags);

        $protectedConst = new ConstantDef('B', Modifiers::PROTECTED, 'php::Int', '2');
        $this->assertSame(Modifiers::PROTECTED, $protectedConst->flags);

        $privateConst = new ConstantDef('C', Modifiers::PRIVATE, 'php::Int', '3');
        $this->assertSame(Modifiers::PRIVATE, $privateConst->flags);
    }
}
