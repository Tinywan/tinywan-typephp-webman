<?php

/**
 * Zend validates the first-seen declaration of a method as an override of
 * every later same-name declaration when interfaces merge — both for
 * `interface J extends I1, I2` and for a class implementing several
 * interfaces without defining the method itself.
 */
class InterfaceMethodCollisionTest extends BaseTest
{
    public function testIncompatibleMultiExtendsIsRejected(): void
    {
        $this->exec(
            'Declaration of `I1::f()` must be compatible with `I2::f()`',
            'interface_multi_extends_incompatible.php'
        );
    }

    public function testUnimplementedCollisionOnClassIsRejected(): void
    {
        $this->exec(
            'Declaration of `I1::f()` must be compatible with `I2::f()`',
            'interface_collision_unimplemented.php'
        );
    }

    public function testCompatibleAndSatisfiedCollisionsAreAccepted(): void
    {
        $this->compile('interface_collision_valid.php');
    }
}
