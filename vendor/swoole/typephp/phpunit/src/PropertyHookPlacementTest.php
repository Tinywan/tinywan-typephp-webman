<?php

/**
 * Zend property-hook placement rules for class/trait properties: no
 * hooks on static or readonly properties, abstract hooked properties
 * only in abstract containers with at least one bodiless hook, and a
 * mandatory body on every non-abstract hook.
 */
class PropertyHookPlacementTest extends BaseTest
{
    public function testHooksOnStaticPropertyAreRejected(): void
    {
        $this->exec('Cannot declare hooks for static property', 'hook_rule_static.php');
    }

    public function testHooksOnReadonlyPropertyAreRejected(): void
    {
        $this->exec('Hooked properties cannot be readonly', 'hook_rule_readonly.php');
    }

    public function testHooksInReadonlyClassAreRejected(): void
    {
        $this->exec('Hooked properties cannot be readonly', 'hook_rule_readonly_class.php');
    }

    public function testAbstractHookedPropertyRequiresAbstractClass(): void
    {
        $this->exec('Non-abstract class `Box` contains abstract hooked property `$x`', 'hook_rule_abstract_nonabstract_class.php');
    }

    public function testAbstractPropertyNeedsAtLeastOneAbstractHook(): void
    {
        $this->exec('Abstract property `Box::$x` must specify at least one abstract hook', 'hook_rule_abstract_all_bodies.php');
    }

    public function testOnlyHookedPropertiesMayBeAbstract(): void
    {
        $this->exec('Only hooked properties may be declared abstract', 'hook_rule_abstract_no_hooks.php');
    }

    public function testNonAbstractHookMustHaveBody(): void
    {
        $this->exec('Non-abstract property hook must have a body', 'hook_rule_bodyless.php');
    }

    public function testWellFormedHooksStillCompile(): void
    {
        $this->compile('hook_rule_valid.php');
    }

    public function testAbstractPrivateHookIsRejected(): void
    {
        // An abstract (bodiless) hook must be implementable by a subclass,
        // which a private property forbids.
        $this->exec('Property hook cannot be both abstract and private', 'hook_rule_abstract_private.php');
    }

    public function testAbstractPrivateHookIsRejectedInTrait(): void
    {
        // Unlike abstract private trait methods, Zend does not exempt traits
        // from the abstract-private hook conflict.
        $this->exec('Property hook cannot be both abstract and private', 'hook_rule_abstract_private_trait.php');
    }

    public function testAbstractFinalHookIsRejected(): void
    {
        // A bodiless hook must be overridable to ever gain a body; it cannot
        // carry final.
        $this->exec('Property hook cannot be both abstract and final', 'hook_rule_abstract_final.php');
    }

    public function testFinalPrivateHookWinsDiagnosticPrecedence(): void
    {
        // `abstract private int $x { final get; }` violates all three rules;
        // Zend reports the final+private conflict first (probed on 8.4.13).
        $this->exec('Property hook cannot be both final and private', 'hook_rule_final_private.php');
    }

    public function testProtectedAbstractHookStaysLegal(): void
    {
        $this->compile('hook_rule_abstract_protected_valid.php');
    }
}
