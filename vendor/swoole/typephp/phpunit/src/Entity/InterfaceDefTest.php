<?php

namespace TypePhp\Tests\Entity;

use PHPUnit\Framework\TestCase;
use TypePhp\Entity\InterfaceDef;
use TypePhp\Entity\MethodDef;

class InterfaceDefTest extends TestCase
{
    public function testConstructWithoutNamespace(): void
    {
        $iface = new InterfaceDef('JsonSerializable');

        $this->assertEquals('JsonSerializable', $iface->name);
        $this->assertEquals('', $iface->namespace);
        $this->assertEquals('', $iface->extends);
    }

    public function testConstructWithNamespace(): void
    {
        $iface = new InterfaceDef('Renderable', 'App\\Contracts');

        $this->assertEquals('Renderable', $iface->name);
        $this->assertEquals('App\\Contracts', $iface->namespace);
    }

    public function testGetNamespacedName(): void
    {
        $iface = new InterfaceDef('Logger', 'App\\Contracts');

        $this->assertEquals('App_Contracts_Logger', $iface->getNamespacedName(true));
        $this->assertEquals('App\\Contracts\\Logger', $iface->getNamespacedName(false));
    }

    public function testExtendsCanBeSet(): void
    {
        $iface = new InterfaceDef('Child');
        $iface->extends = 'Parent';

        $this->assertEquals('Parent', $iface->extends);
    }

    public function testTracksMethodsCaseInsensitively(): void
    {
        $iface = new InterfaceDef('Runnable');
        $iface->addMethod(new MethodDef(0, 'run'));

        $this->assertTrue($iface->hasMethod('run'));
        $this->assertTrue($iface->hasMethod('RUN'));
        $this->assertArrayHasKey('run', $iface->methods);
    }

    public function testTracksMultipleParentInterfaces(): void
    {
        $iface = new InterfaceDef('Child');
        $iface->extendsList = ['ParentA', 'ParentB'];

        $this->assertSame(['ParentA', 'ParentB'], $iface->extendsList);
    }
}
