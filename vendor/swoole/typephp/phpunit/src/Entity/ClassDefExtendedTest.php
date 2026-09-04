<?php

namespace TypePhp\Tests\Entity;

use PHPUnit\Framework\TestCase;
use TypePhp\Entity\ClassDef;
use TypePhp\Entity\MethodDef;
use PhpParser\Modifiers;
use PhpParser\Node\Stmt\Trait_;

class ClassDefExtendedTest extends TestCase
{
    public function testEnumFlag(): void
    {
        $class = new ClassDef('Status', Modifiers::PUBLIC);
        $this->assertFalse($class->enum);
        $class->enum = true;
        $this->assertTrue($class->enum);
    }

    public function testInheritedFromInternalClass(): void
    {
        $class = new ClassDef('MyClass', Modifiers::PUBLIC);
        $this->assertFalse($class->inheritedFromInternalClass);
        $class->inheritedFromInternalClass = true;
        $this->assertTrue($class->inheritedFromInternalClass);
    }

    public function testCtorInitAndClean(): void
    {
        $class = new ClassDef('Service', Modifiers::PUBLIC);
        $this->assertEquals('', $class->ctorInit);
        $this->assertEquals('', $class->ctorClean);

        $class->ctorInit = 'property_1 = 0;';
        $class->ctorClean = 'property_1 = 0;';
        $this->assertEquals('property_1 = 0;', $class->ctorInit);
        $this->assertEquals('property_1 = 0;', $class->ctorClean);
    }

    public function testRequireCtorFlag(): void
    {
        $class = new ClassDef('Entity', Modifiers::PUBLIC);
        $this->assertFalse($class->requireCtor);
        $class->requireCtor = true;
        $this->assertTrue($class->requireCtor);
    }

    public function testTraitAssociation(): void
    {
        $class = new ClassDef('User', Modifiers::PUBLIC);
        $this->assertNull($class->trait);

        $traitStmt = new Trait_('SomeTrait');
        $class->trait = $traitStmt;
        $this->assertSame($traitStmt, $class->trait);
    }

    public function testTraitAliasesAndIgnoredCanBeSet(): void
    {
        $class = new ClassDef('User', Modifiers::PUBLIC);
        $class->traitAliases['full\\trait::method'][] = [
            'group' => '1:0',
            'trait' => 'Full\\Trait',
            'method' => 'method',
            'newName' => 'newName',
            'newModifier' => 0,
        ];
        $class->traitIgnored['full\\trait::other'][] = [
            'winnerTrait' => 'Full\\Winner',
            'loserTrait' => 'Full\\Trait',
            'method' => 'other',
        ];

        $this->assertSame('newName', $class->traitAliases['full\\trait::method'][0]['newName']);
        $this->assertSame('Full\\Winner', $class->traitIgnored['full\\trait::other'][0]['winnerTrait']);
    }

    public function testExtendsCanBeSet(): void
    {
        $class = new ClassDef('Derived', Modifiers::PUBLIC);
        $class->extends = 'App\\Entity\\Base';
        $this->assertEquals('App\\Entity\\Base', $class->extends);
    }

    public function testImplementsCanBeSet(): void
    {
        $class = new ClassDef('Service', Modifiers::PUBLIC);
        $class->implements = ['Serializable', 'JsonSerializable'];
        $this->assertContains('Serializable', $class->implements);
        $this->assertContains('JsonSerializable', $class->implements);
    }

    public function testMultipleMethodsWithSameNameCaseInsensitive(): void
    {
        $class = new ClassDef('Foo', Modifiers::PUBLIC);
        $method = new MethodDef(Modifiers::PUBLIC, 'MyMethod');

        $class->addMethod($method);
        // Adding same method name (case-insensitive) overwrites
        $this->assertTrue($class->hasMethod('MYMETHOD'));
        $this->assertTrue($class->hasMethod('mymethod'));
        $this->assertTrue($class->hasMethod('MyMethod'));
    }

    public function testFinalClassModifier(): void
    {
        $class = new ClassDef('FinalClass', Modifiers::PUBLIC | Modifiers::FINAL);
        $this->assertTrue((bool) ($class->flags & Modifiers::FINAL));
        $this->assertFalse($class->isAbstract());
    }

    public function testReadonlyClassModifier(): void
    {
        $class = new ClassDef('ReadonlyClass', Modifiers::PUBLIC | Modifiers::READONLY);
        $this->assertTrue((bool) ($class->flags & Modifiers::READONLY));
    }

    public function testFlagsPropertyIsAccessible(): void
    {
        $class = new ClassDef('Foo', Modifiers::PUBLIC);
        $this->assertSame(Modifiers::PUBLIC, $class->flags);

        $class->flags = Modifiers::PROTECTED;
        $this->assertSame(Modifiers::PROTECTED, $class->flags);
    }

    public function testPropertiesDefaultEmptyArray(): void
    {
        $class = new ClassDef('Foo', Modifiers::PUBLIC);
        $this->assertIsArray($class->properties);
        $this->assertEmpty($class->properties);
        $this->assertIsArray($class->constants);
        $this->assertEmpty($class->constants);
        $this->assertIsArray($class->methods);
        $this->assertEmpty($class->methods);
    }

    public function testPropertyContextIsUniquePerInstance(): void
    {
        $class1 = new ClassDef('Foo', Modifiers::PUBLIC);
        $class2 = new ClassDef('Bar', Modifiers::PUBLIC);

        $this->assertNotSame($class1->propertyContext, $class2->propertyContext);
    }
}
