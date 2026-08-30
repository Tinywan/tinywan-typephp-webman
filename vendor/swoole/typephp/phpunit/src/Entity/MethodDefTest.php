<?php

namespace TypePhp\Tests\Entity;

use PHPUnit\Framework\TestCase;
use TypePhp\Entity\MethodDef;
use TypePhp\Entity\FunctionDef;
use PhpParser\Modifiers;

class MethodDefTest extends TestCase
{
    public function testConstruct(): void
    {
        $method = new MethodDef(Modifiers::PUBLIC, 'handle');

        $this->assertEquals('handle', $method->name);
        $this->assertSame(Modifiers::PUBLIC, $method->flags);
        $this->assertNull($method->functionDef);
    }

    public function testGetReturnType(): void
    {
        $method = new MethodDef(Modifiers::PUBLIC, 'getValue');
        $fn = new FunctionDef('getValue', 'php::Int', '');
        $method->functionDef = $fn;

        $this->assertEquals('php::Int', $method->getReturnType());
    }

    public function testGetReturnTypeVoid(): void
    {
        $method = new MethodDef(Modifiers::PROTECTED, 'execute');
        $fn = new FunctionDef('execute', 'void', '');
        $method->functionDef = $fn;

        $this->assertEquals('void', $method->getReturnType());
    }

    public function testFlags(): void
    {
        $publicMethod = new MethodDef(Modifiers::PUBLIC, 'pub');
        $this->assertSame(Modifiers::PUBLIC, $publicMethod->flags);

        $protectedMethod = new MethodDef(Modifiers::PROTECTED, 'prot');
        $this->assertSame(Modifiers::PROTECTED, $protectedMethod->flags);

        $privateMethod = new MethodDef(Modifiers::PRIVATE, 'priv');
        $this->assertSame(Modifiers::PRIVATE, $privateMethod->flags);
    }
}
