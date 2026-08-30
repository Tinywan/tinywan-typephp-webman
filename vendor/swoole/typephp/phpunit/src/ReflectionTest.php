<?php

namespace TypePhp\Tests;

use PHPUnit\Framework\TestCase;
use TypePhp\Resolver\Reflection;

class ReflectionTest extends TestCase
{
    public function testGetFunctionReturnTypeUnionReturnsNull(): void
    {
        eval('function php_aot_reflection_union_return(): int|string { return 1; }');
        $this->assertNull(Reflection::getFunctionReturnType('php_aot_reflection_union_return'));
    }

    public function testGetFunctionReturnTypeIntersectionReturnsNull(): void
    {
        eval('
            interface PhpAotReflectionI1 {}
            interface PhpAotReflectionI2 {}
            final class PhpAotReflectionBoth implements PhpAotReflectionI1, PhpAotReflectionI2 {}
            function php_aot_reflection_intersection_return(): PhpAotReflectionI1&PhpAotReflectionI2 {
                return new PhpAotReflectionBoth();
            }
        ');
        $this->assertNull(Reflection::getFunctionReturnType('php_aot_reflection_intersection_return'));
    }

    public function testIsInternalClass(): void
    {
        // Standard PHP internal classes
        $this->assertTrue(Reflection::isInternalClass('stdClass'));
        $this->assertTrue(Reflection::isInternalClass('Exception'));
        $this->assertTrue(Reflection::isInternalClass('DateTime'));
    }

    public function testIsInternalClassCaseInsensitive(): void
    {
        $this->assertTrue(Reflection::isInternalClass('stdclass'));
        $this->assertTrue(Reflection::isInternalClass('EXCEPTION'));
    }

    public function testIsInternalClassNonexistent(): void
    {
        $this->assertFalse(Reflection::isInternalClass('NonExistentClass_' . uniqid()));
    }

    public function testIsInternalInterface(): void
    {
        $this->assertTrue(Reflection::isInternalInterface('Iterator'));
        $this->assertTrue(Reflection::isInternalInterface('ArrayAccess'));
        $this->assertTrue(Reflection::isInternalInterface('JsonSerializable'));
    }

    public function testIsInternalInterfaceNonexistent(): void
    {
        $this->assertFalse(Reflection::isInternalInterface('NonExistentIface_' . uniqid()));
    }

    public function testGetFunction(): void
    {
        $ref = Reflection::getFunction('strlen');
        $this->assertNotNull($ref);
        $this->assertInstanceOf(\ReflectionFunction::class, $ref);
        $this->assertEquals('strlen', $ref->getName());
    }

    public function testGetFunctionNonexistent(): void
    {
        $ref = Reflection::getFunction('nonexistent_func_' . uniqid());
        $this->assertNull($ref);
    }

    public function testGetFunctionCaches(): void
    {
        $ref1 = Reflection::getFunction('strtolower');
        $ref2 = Reflection::getFunction('strtolower');
        $this->assertSame($ref1, $ref2);
    }

    public function testGetClass(): void
    {
        $ref = Reflection::getClass('stdClass');
        $this->assertNotNull($ref);
        $this->assertInstanceOf(\ReflectionClass::class, $ref);
    }

    public function testGetClassNonexistent(): void
    {
        $ref = Reflection::getClass('NonExistent_' . uniqid());
        $this->assertNull($ref);
    }

    public function testGetFunctionReturnType(): void
    {
        $type = Reflection::getFunctionReturnType('strlen');
        $this->assertEquals('int', $type);
    }

    public function testGetFunctionReturnTypeNonexistent(): void
    {
        $type = Reflection::getFunctionReturnType('nonexistent_' . uniqid());
        $this->assertNull($type);
    }

    public function testGetFunctionParameter(): void
    {
        $param = Reflection::getFunctionParameter('strlen', 0);
        $this->assertNotNull($param);
        $this->assertInstanceOf(\ReflectionParameter::class, $param);
    }

    public function testGetFunctionParameterOutOfRange(): void
    {
        $param = Reflection::getFunctionParameter('strlen', 999);
        $this->assertNull($param);
    }

    public function testHasMethod(): void
    {
        $this->assertTrue(Reflection::hasMethod('Exception', 'getMessage'));
        $this->assertTrue(Reflection::hasMethod('Exception', '__construct'));

        // Method that doesn't exist
        $this->assertFalse(Reflection::hasMethod('Exception', 'nonexistent_' . uniqid()));
    }

    public function testGetClassMethodModifiers(): void
    {
        $modifiers = Reflection::getClassMethodModifiers('DateTime', 'format');
        $this->assertNotNull($modifiers);
        $this->assertIsInt($modifiers);
    }

    public function testGetClassMethodModifiersNonexistent(): void
    {
        $modifiers = Reflection::getClassMethodModifiers('NonExistent_' . uniqid(), 'test');
        $this->assertNull($modifiers);
    }

    public function testGetMethodReturnType(): void
    {
        $type = Reflection::getMethodReturnType('Exception', 'getMessage');
        $this->assertEquals('string', $type);
    }

    public function testGetMethodReturnTypeUnionReturnsNull(): void
    {
        eval('
            class PhpAotReflectionUnionMethodReturn {
                public function value(): int|string {
                    return 1;
                }
            }
        ');
        $this->assertNull(Reflection::getMethodReturnType('PhpAotReflectionUnionMethodReturn', 'value'));
    }

    public function testGetMethodReturnTypeIntersectionReturnsNull(): void
    {
        eval('
            interface PhpAotReflectionMethodI1 {}
            interface PhpAotReflectionMethodI2 {}
            final class PhpAotReflectionMethodBoth implements PhpAotReflectionMethodI1, PhpAotReflectionMethodI2 {}
            class PhpAotReflectionIntersectionMethodReturn {
                public function value(): PhpAotReflectionMethodI1&PhpAotReflectionMethodI2 {
                    return new PhpAotReflectionMethodBoth();
                }
            }
        ');
        $this->assertNull(Reflection::getMethodReturnType('PhpAotReflectionIntersectionMethodReturn', 'value'));
    }

    public function testGetMethodReturnTypeNonexistent(): void
    {
        $type = Reflection::getMethodReturnType('NonExistent_' . uniqid(), 'test');
        $this->assertNull($type);
    }

    public function testIsAbstractClass(): void
    {
        $this->assertFalse(Reflection::isAbstractClass('stdClass'));
        $this->assertFalse(Reflection::isAbstractClass('NonExistent_' . uniqid()));
    }
}
