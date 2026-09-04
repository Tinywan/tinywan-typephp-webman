<?php

use TypePhp\Exception\TestError;

/**
 * Zend exempts constructors from LSP checks against a CONCRETE parent
 * constructor, but still forbids overriding a FINAL parent constructor (even a
 * final private one), and validates the signature against an ABSTRACT parent
 * constructor exactly like an interface constructor.
 */
class ConstructorOverrideTest extends BaseTest
{
    public function testConcreteAndPrivateParentConstructorsAreExempt(): void
    {
        $this->compile('ctor_override_valid.php');
    }

    public function testFinalParentConstructorCannotBeOverridden(): void
    {
        $this->exec(
            'Cannot override final method `A::__construct()`',
            'ctor_override_final.php',
        );
    }

    public function testFinalPrivateParentConstructorCannotBeOverridden(): void
    {
        $this->exec(
            'Cannot override final method `A::__construct()`',
            'ctor_override_final_private.php',
        );
    }

    public function testAbstractParentConstructorSignatureIsEnforced(): void
    {
        $this->exec(
            'Declaration of `B::__construct()` must be compatible with `A::__construct()`',
            'ctor_override_abstract_incompatible.php',
        );
    }
}
