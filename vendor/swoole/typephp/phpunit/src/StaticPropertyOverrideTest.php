<?php

use TypePhp\Exception\TestError;

/**
 * A static property and an instance property are different kinds of storage;
 * Zend forbids redeclaring one as the other in either direction ("Cannot
 * redeclare static A::$x as non static B::$x" and vice versa).
 */
class StaticPropertyOverrideTest extends BaseTest
{
    public function testMatchingStaticnessCompiles(): void
    {
        $this->compile('property_static_match.php');
    }

    public function testStaticCannotBecomeInstance(): void
    {
        $this->exec(
            'Cannot redeclare static `A::$x` as non static `B::$x`',
            'property_static_mismatch.php',
        );
    }

    public function testInstanceCannotBecomeStatic(): void
    {
        $this->exec(
            'Cannot redeclare non static `A::$x` as static `B::$x`',
            'property_nonstatic_mismatch.php',
        );
    }
}
