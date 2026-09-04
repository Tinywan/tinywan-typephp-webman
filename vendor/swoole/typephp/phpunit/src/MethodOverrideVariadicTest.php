<?php

use TypePhp\Exception\TestError;

/**
 * Zend's inheritance check lets a trailing child variadic stand in for every
 * remaining parent parameter position (the decorator pattern), provided the
 * variadic's type is contravariant-compatible with each covered position and
 * by-ref-ness matches. A variadic parent still requires a variadic child, and
 * when the parent is variadic every extra child parameter is validated against
 * the parent's variadic slot.
 */
class MethodOverrideVariadicTest extends BaseTest
{
    public function testTrailingChildVariadicAbsorbsParentParameters(): void
    {
        $this->compile('override_variadic_absorbs_params.php');
    }

    public function testChildVariadicTypeMustCoverEveryAbsorbedPosition(): void
    {
        $this->exec(
            'Declaration of `B::f()` must be compatible with `A::f()`',
            'override_variadic_bad_type.php',
        );
    }

    public function testChildVariadicMustMatchByRefOfAbsorbedPosition(): void
    {
        $this->exec(
            'Declaration of `B::f()` must be compatible with `A::f()`',
            'override_variadic_byref_mismatch.php',
        );
    }

    public function testVariadicParentRequiresVariadicChild(): void
    {
        $this->exec(
            'Declaration of `B::f()` must be compatible with `A::f()`',
            'override_parent_variadic_child_not.php',
        );
    }

    public function testExtraChildParametersCheckedAgainstParentVariadic(): void
    {
        $this->compile('override_parent_variadic_child_extra.php');
    }
}
