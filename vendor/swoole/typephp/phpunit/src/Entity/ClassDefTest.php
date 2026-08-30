<?php

namespace TypePhp\Tests\Entity;

use PHPUnit\Framework\TestCase;
use TypePhp\Entity\ClassDef;
use TypePhp\Entity\MethodDef;
use TypePhp\Entity\PropertyDef;
use TypePhp\Entity\ConstantDef;
use TypePhp\Entity\FunctionDef;
use PhpParser\Modifiers;

class ClassDefTest extends TestCase
{
    public function testConstructDefaults(): void
    {
        $class = new ClassDef('Foo', Modifiers::PUBLIC);
        $this->assertEquals('Foo', $class->name);
        $this->assertEquals('', $class->namespace);
        $this->assertSame(Modifiers::PUBLIC, $class->flags);
        $this->assertEquals('', $class->extends);
        $this->assertEmpty($class->implements);
        $this->assertEmpty($class->methods);
        $this->assertEmpty($class->properties);
        $this->assertEmpty($class->constants);
        $this->assertFalse($class->enum);
        $this->assertFalse($class->requireCtor);
        $this->assertFalse($class->inheritedFromInternalClass);
        $this->assertFalse($class->nativeObject);
        $this->assertNull($class->trait);
    }

    public function testConstructWithNamespace(): void
    {
        $class = new ClassDef('Bar', Modifiers::PUBLIC, 'App\\Lib');
        $this->assertEquals('Bar', $class->name);
        $this->assertEquals('App\\Lib', $class->namespace);
    }

    public function testPropertyContextIsInitialized(): void
    {
        $class = new ClassDef('Foo', Modifiers::PUBLIC);
        $this->assertNotNull($class->propertyContext);
        $this->assertInstanceOf(\TypePhp\Context\FunctionContext::class, $class->propertyContext);
    }

    public function testGetNamespacedNameInheritedFromClassLikeDef(): void
    {
        $class = new ClassDef('Foo', Modifiers::PUBLIC, 'A\\B');
        $this->assertEquals('A_B_Foo', $class->getNamespacedName(true));
        $this->assertEquals('A\\B\\Foo', $class->getNamespacedName(false));
    }

    public function testAddAndHasMethod(): void
    {
        $class = new ClassDef('Foo', Modifiers::PUBLIC);
        $method = new MethodDef(Modifiers::PUBLIC, 'bar');

        $this->assertFalse($class->hasMethod('bar'));

        $class->addMethod($method);
        $this->assertTrue($class->hasMethod('bar'));
        $this->assertTrue($class->hasMethod('BAR')); // case-insensitive
        $this->assertSame($method, $class->getMethod('bar'));
    }

    public function testGetMethodCaseInsensitive(): void
    {
        $class = new ClassDef('Foo', Modifiers::PUBLIC);
        $method = new MethodDef(Modifiers::PUBLIC, 'MyMethod');

        $class->addMethod($method);
        $this->assertSame($method, $class->getMethod('mymethod'));
        $this->assertSame($method, $class->getMethod('MYMETHOD'));
    }

    public function testHasMethodNotFound(): void
    {
        $class = new ClassDef('Foo', Modifiers::PUBLIC);
        $this->assertFalse($class->hasMethod('nonexistent'));
    }

    public function testHasProperty(): void
    {
        $class = new ClassDef('Foo', Modifiers::PUBLIC);
        $prop = new PropertyDef('name', Modifiers::PUBLIC, 'php::Str');
        $class->properties['name'] = $prop;

        $this->assertTrue($class->hasProperty('name'));
        $this->assertFalse($class->hasProperty('missing'));
        $this->assertSame($prop, $class->getProperty('name'));
    }

    public function testHasConstant(): void
    {
        $class = new ClassDef('Foo', Modifiers::PUBLIC);
        $const = new ConstantDef('VERSION', Modifiers::PUBLIC, 'php::Str', '"1.0"');
        $class->constants['VERSION'] = $const;

        $this->assertTrue($class->hasConstant('VERSION'));
        $this->assertFalse($class->hasConstant('MISSING'));
        $this->assertSame($const, $class->getConstant('VERSION'));
    }

    public function testIsAbstract(): void
    {
        $abstractClass = new ClassDef('AbstractFoo', Modifiers::ABSTRACT);
        $this->assertTrue($abstractClass->isAbstract());

        $concreteClass = new ClassDef('ConcreteFoo', Modifiers::PUBLIC);
        $this->assertFalse($concreteClass->isAbstract());
    }

    public function testRequireCtorAndEnumDefaults(): void
    {
        $class = new ClassDef('Foo', Modifiers::PUBLIC);
        $this->assertFalse($class->requireCtor);
        $this->assertFalse($class->enum);
    }

    public function testTraitAliasesAndIgnoredDefaults(): void
    {
        $class = new ClassDef('Foo', Modifiers::PUBLIC);
        $this->assertEmpty($class->traitAliases);
        $this->assertEmpty($class->traitIgnored);
    }

    public function testImplementsAndExtendsDefaults(): void
    {
        $class = new ClassDef('Foo', Modifiers::PUBLIC);
        $this->assertEmpty($class->implements);
        $this->assertEquals('', $class->extends);
    }
}
