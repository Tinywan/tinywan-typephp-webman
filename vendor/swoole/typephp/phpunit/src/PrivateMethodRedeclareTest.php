<?php

use TypePhp\Exception\TestError;

/**
 * Private methods are not inherited in Zend: a child class may redeclare one
 * with any signature, visibility or staticness, and FINAL is ignored on
 * non-constructor private methods. Generated code stays correct because
 * private calls devirtualize to the declaring class's body — PHP's own
 * private-scope binding — and Native classes give private methods no virtual
 * slot.
 */
class PrivateMethodRedeclareTest extends BaseTest
{
    public function testPrivateMethodsMayBeRedeclaredFreely(): void
    {
        $this->compile('private_redeclare_valid.php');
    }

    public function testFinalPrivateConstructorIsStillProtectedFromOverride(): void
    {
        $this->exec(
            'Cannot override final method `A::__construct()`',
            'ctor_override_final_private.php',
        );
    }
}
