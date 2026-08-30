<?php

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;
use TypePhp\Entity\ArgInfo;

class ArgInfoTest extends TestCase
{
    public function testDefaultBooleanProperties(): void
    {
        $arg = new ArgInfo();
        // Set required string properties first (they have no defaults)
        $arg->name = '';
        $arg->type = '';

        // default values for implicitly-initialized fields
        $this->assertEquals('', $arg->default);
        $this->assertNull($arg->defaultValue);
        $this->assertEquals('', $arg->class);
        $this->assertFalse($arg->byRef);
        $this->assertFalse($arg->variadic);
        $this->assertFalse($arg->nullable);
        $this->assertFalse($arg->property);
    }

    public function testNameAndType(): void
    {
        $arg = new ArgInfo();
        $arg->name = 'value';
        $arg->type = 'php::Int';

        $this->assertEquals('value', $arg->name);
        $this->assertEquals('php::Int', $arg->type);
    }

    public function testDefaultValues(): void
    {
        $arg = new ArgInfo();
        $arg->default = '0';

        $this->assertEquals('0', $arg->default);
    }

    public function testClass(): void
    {
        $arg = new ArgInfo();
        $arg->class = 'DateTime';

        $this->assertEquals('DateTime', $arg->class);
    }

    public function testByRef(): void
    {
        $arg = new ArgInfo();
        $this->assertFalse($arg->byRef);

        $arg->byRef = true;
        $this->assertTrue($arg->byRef);
    }

    public function testVariadic(): void
    {
        $arg = new ArgInfo();
        $this->assertFalse($arg->variadic);

        $arg->variadic = true;
        $this->assertTrue($arg->variadic);
    }

    public function testNullable(): void
    {
        $arg = new ArgInfo();
        $this->assertFalse($arg->nullable);

        $arg->nullable = true;
        $this->assertTrue($arg->nullable);
    }

    public function testProperty(): void
    {
        $arg = new ArgInfo();
        $this->assertFalse($arg->property);

        $arg->property = true;
        $this->assertTrue($arg->property);
    }

    public function testAllFlagsCombined(): void
    {
        $arg = new ArgInfo();
        $arg->name = 'args';
        $arg->type = 'php::Var';
        $arg->variadic = true;
        $arg->byRef = true;
        $arg->nullable = true;

        $this->assertEquals('args', $arg->name);
        $this->assertEquals('php::Var', $arg->type);
        $this->assertTrue($arg->variadic);
        $this->assertTrue($arg->byRef);
        $this->assertTrue($arg->nullable);
    }
}
