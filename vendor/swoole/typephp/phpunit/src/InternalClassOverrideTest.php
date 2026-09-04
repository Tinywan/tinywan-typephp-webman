<?php

use TypePhp\Exception\TestError;

/**
 * Overrides of methods inherited from Zend built-in classes must be validated
 * against the host reflection signature, following the same inheritance rules
 * Zend applies to userland parents. Tentative return types are exempt from the
 * covariance check because Zend only deprecates a tentative mismatch.
 */
class InternalClassOverrideTest extends BaseTest
{
    public function testCompatibleInternalOverridesCompile(): void
    {
        $this->compile('internal_override_valid.php');
    }

    public function testParameterCannotBeNarrowed(): void
    {
        $this->exec(
            'Declaration of `C::offsetGet()` must be compatible with `ArrayObject::offsetGet()`',
            'internal_override_param_narrowed.php',
        );
    }

    public function testStaticnessMustMatch(): void
    {
        $this->exec(
            'Declaration of `C::count()` must be compatible with `ArrayObject::count()`',
            'internal_override_static_mismatch.php',
        );
    }

    public function testVisibilityCannotBeNarrowed(): void
    {
        $this->exec(
            'Declaration of `C::count()` must be compatible with `ArrayObject::count()`',
            'internal_override_visibility_narrowed.php',
        );
    }

    public function testRealReturnTypeMustBeCovariant(): void
    {
        $this->exec(
            'Declaration of `C::getMicrosecond()` must be compatible with `DateTime::getMicrosecond()`',
            'internal_override_real_return_mismatch.php',
        );
    }
}
