<?php

namespace TypePhp\Tests\Entity;

use PHPUnit\Framework\TestCase;
use TypePhp\Entity\ClassLikeDef;
use TypePhp\Entity\ClassDef;
use TypePhp\Entity\InterfaceDef;
use PhpParser\Modifiers;

class ClassLikeDefTest extends TestCase
{
    public function testConstructWithoutNamespace(): void
    {
        $def = new ClassLikeDef('Foo');
        $this->assertEquals('Foo', $def->name);
        $this->assertEquals('', $def->namespace);
        $this->assertEquals('', $def->extends);
    }

    public function testConstructWithNamespace(): void
    {
        $def = new ClassLikeDef('Foo', 'App\\Bar');
        $this->assertEquals('Foo', $def->name);
        $this->assertEquals('App\\Bar', $def->namespace);
    }

    public function testGetNamespacedNameWithoutNamespace(): void
    {
        $def = new ClassLikeDef('Foo');
        $this->assertEquals('Foo', $def->getNamespacedName());
        $this->assertEquals('Foo', $def->getNamespacedName(false));
    }

    public function testGetNamespacedNameSymbolic(): void
    {
        $def = new ClassLikeDef('Foo', 'App\\Bar');
        // symbolic: backslashes → underscores → App_Bar_Foo
        $this->assertEquals('App_Bar_Foo', $def->getNamespacedName(true));
    }

    public function testGetNamespacedNameNonSymbolic(): void
    {
        $def = new ClassLikeDef('Foo', 'App\\Bar');
        $this->assertEquals('App\\Bar\\Foo', $def->getNamespacedName(false));
    }
}
