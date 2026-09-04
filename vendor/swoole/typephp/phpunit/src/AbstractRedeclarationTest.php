<?php

/**
 * An abstract method declared by the class itself participates in the parent
 * checks: it cannot turn a concrete inherited method abstract, and it must
 * stay compatible with an inherited abstract contract. Trait requirements are
 * exempt (an inherited concrete method satisfies them).
 */
class AbstractRedeclarationTest extends BaseTest
{
    public function testConcreteParentMethodCannotBeMadeAbstract(): void
    {
        $this->exec(
            'Cannot make non abstract method `A::f()` abstract in class `B`',
            'abstract_redeclare_concrete.php'
        );
    }

    public function testAbstractRedeclarationMustStayCompatible(): void
    {
        $this->exec(
            'Declaration of `B::f()` must be compatible with `A::f()`',
            'abstract_redeclare_incompatible.php'
        );
    }

    public function testConcreteBuiltinMethodCannotBeMadeAbstract(): void
    {
        $this->exec(
            'Cannot make non abstract method `ArrayObject::count()` abstract in class `B`',
            'abstract_redeclare_internal.php'
        );
    }

    public function testValidAbstractRedeclarations(): void
    {
        $this->compile('abstract_redeclare_valid.php');
    }
}
