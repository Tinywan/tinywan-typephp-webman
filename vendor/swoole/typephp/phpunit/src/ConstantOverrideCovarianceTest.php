<?php

use TypePhp\Exception\TestError;

/**
 * PHP 8.3 typed class constants are covariant: an override may narrow the
 * declared type (int|string -> int, mixed -> string, ?int -> int) but never
 * widen it or move to an unrelated type.
 */
class ConstantOverrideCovarianceTest extends BaseTest
{
    public function testNarrowingDeclaredTypeCompiles(): void
    {
        $this->compile('const_override_covariant.php');
    }

    public function testNarrowingDnfDeclaredTypeCompiles(): void
    {
        $this->compile('const_override_dnf_covariant.php');
    }

    public function testWideningDeclaredTypeIsRejected(): void
    {
        $this->exec(
            'Declaration of `B::X` must be compatible with `A::X`',
            'const_override_widened.php',
        );
    }

    public function testUnrelatedDeclaredTypeIsRejected(): void
    {
        $this->exec(
            'Declaration of `B::X` must be compatible with `A::X`',
            'const_override_unrelated.php',
        );
    }
}
