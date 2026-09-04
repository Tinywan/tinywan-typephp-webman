<?php

/**
 * Zend interface member declaration rules: implicitly public abstract
 * methods without bodies, public constants, no explicit abstract on
 * hooked properties, and extends restricted to (distinct) interfaces.
 */
class InterfaceDeclarationRulesTest extends BaseTest
{
    public function testInterfaceMethodCannotContainBody(): void
    {
        $this->exec('Interface function `Runner::run()` cannot contain body', 'interface_rule_body.php');
    }

    public function testInterfaceMethodMustNotBeFinal(): void
    {
        $this->exec('Interface method `Runner::run()` must not be final', 'interface_rule_final.php');
    }

    public function testInterfaceMethodMustBePublic(): void
    {
        $this->exec('Access type for interface method `Runner::run()` must be public', 'interface_rule_private.php');
    }

    public function testInterfaceMethodMustNotBeAbstract(): void
    {
        $this->exec('Interface method `Runner::run()` must not be abstract', 'interface_rule_abstract.php');
    }

    public function testInterfaceConstantMustBePublic(): void
    {
        $this->exec('Access type for interface constant `Runner::SPEED` must be public', 'interface_rule_const_private.php');
    }

    public function testInterfaceCannotExtendClass(): void
    {
        $this->exec('`Runner` cannot implement `Base` - it is not an interface', 'interface_rule_extends_class.php');
    }

    public function testExtendsClassDeclaredLaterIsRejected(): void
    {
        $this->exec('`Late` cannot implement `Impl` - it is not an interface', 'interface_rule_extends_class_forward.php');
    }

    public function testInterfaceCannotExtendSameInterfaceTwice(): void
    {
        $this->exec('Interface `Runner` cannot implement previously implemented interface `A`', 'interface_rule_extends_dup.php');
    }

    public function testInterfacePropertyCannotBeExplicitlyAbstract(): void
    {
        $this->exec('Property in interface cannot be explicitly abstract', 'interface_rule_prop_abstract.php');
    }

    public function testCyclicExtendsGraphIsRejectedPromptly(): void
    {
        // Zend cannot even declare such a graph (`Interface "B" not found`);
        // ahead-of-time the cycle exists, so the merged-member table builders
        // must detect it instead of recursing forever.
        $this->exec('Interface inheritance cycle detected at `B`', 'interface_extends_cycle.php');
    }
}
