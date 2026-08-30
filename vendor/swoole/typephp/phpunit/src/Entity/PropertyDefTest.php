<?php

namespace TypePhp\Tests\Entity;

use PHPUnit\Framework\TestCase;
use TypePhp\Entity\PropertyDef;
use PhpParser\Modifiers;

class PropertyDefTest extends TestCase
{
    public function testConstructWithDefaults(): void
    {
        $prop = new PropertyDef('count', Modifiers::PUBLIC, 'php::Int');

        $this->assertEquals('count', $prop->name);
        $this->assertSame(Modifiers::PUBLIC, $prop->flags);
        $this->assertEquals('php::Int', $prop->type);
        $this->assertNull($prop->default);
        $this->assertFalse($prop->nullable);
        $this->assertEquals('', $prop->class);
    }

    public function testConstructWithDefaultValue(): void
    {
        $prop = new PropertyDef('count', Modifiers::PUBLIC, 'php::Int', '0');

        $this->assertEquals('0', $prop->default);
    }

    public function testConstructWithNullable(): void
    {
        $prop = new PropertyDef('name', Modifiers::PUBLIC, 'php::Str', null, true);

        $this->assertTrue($prop->nullable);
    }

    public function testIsPrivate(): void
    {
        $privateProp = new PropertyDef('secret', Modifiers::PRIVATE, 'php::Str');
        $this->assertTrue($privateProp->isPrivate());
        $this->assertFalse($privateProp->isProtected());
        $this->assertFalse($privateProp->isPublic());
    }

    public function testIsProtected(): void
    {
        $protectedProp = new PropertyDef('value', Modifiers::PROTECTED, 'php::Int');
        $this->assertFalse($protectedProp->isPrivate());
        $this->assertTrue($protectedProp->isProtected());
        $this->assertFalse($protectedProp->isPublic());
    }

    public function testIsPublic(): void
    {
        $publicProp = new PropertyDef('name', Modifiers::PUBLIC, 'php::Str');
        $this->assertFalse($publicProp->isPrivate());
        $this->assertFalse($publicProp->isProtected());
        $this->assertTrue($publicProp->isPublic());

        // PUBLIC flag is 0, so no-flag properties are also public
        $impliedPublic = new PropertyDef('name', 0, 'php::Str');
        $this->assertTrue($impliedPublic->isPublic());
    }

    public function testIsStatic(): void
    {
        $staticProp = new PropertyDef('counter', Modifiers::PUBLIC | Modifiers::STATIC, 'php::Int');
        $this->assertTrue($staticProp->isStatic());

        $instanceProp = new PropertyDef('counter', Modifiers::PUBLIC, 'php::Int');
        $this->assertFalse($instanceProp->isStatic());
    }

    public function testClassProperty(): void
    {
        $prop = new PropertyDef('data', Modifiers::PUBLIC, 'php::Var');
        $this->assertEquals('', $prop->class);

        $prop->class = 'App\\Entity\\User';
        $this->assertEquals('App\\Entity\\User', $prop->class);
    }
}
