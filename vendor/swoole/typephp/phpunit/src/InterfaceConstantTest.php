<?php

use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

/**
 * Interface constants are real contracts in Zend: a final one cannot be
 * overridden anywhere below (the original declaring interface is kept in the
 * inherited constants table), a typed one is covariant, an override must stay
 * public, and the same name arriving from two different declarations is
 * ambiguous unless the class declares the constant itself.
 */
class InterfaceConstantTest extends BaseTest
{
    public function testValidInterfaceConstantPatternsCompile(): void
    {
        $this->compile('interface_const_valid.php');
    }

    public function testTypedInterfaceConstantMustBeCovariant(): void
    {
        $this->exec(
            'Declaration of `C::X` must be compatible with `I::X`',
            'interface_const_type_mismatch.php',
        );
    }

    public function testFinalInterfaceConstantCannotBeOverridden(): void
    {
        $this->exec(
            '`C::X` cannot override final constant `I::X`',
            'interface_const_final_override.php',
        );
    }

    public function testFinalInterfaceConstantBindsTransitiveSubclasses(): void
    {
        $this->exec(
            '`C::X` cannot override final constant `I::X`',
            'interface_const_final_via_parent.php',
        );
    }

    public function testSameConstantFromTwoInterfacesIsAmbiguous(): void
    {
        $this->exec(
            'Class `C` inherits both `I1::X` and `I2::X`, which is ambiguous',
            'interface_const_ambiguous.php',
        );
    }

    public function testInterfaceConstantOverrideMustStayPublic(): void
    {
        $this->exec(
            'Access level to `C::X` must be public (as in interface `I`)',
            'interface_const_visibility.php',
        );
    }

    public function testInterfaceExtendingInterfaceChecksConstantTypes(): void
    {
        $this->exec(
            'Declaration of `J::X` must be compatible with `I::X`',
            'interface_extends_const_incompatible.php',
        );
    }

    public function testEnumCannotOverrideFinalInterfaceConstant(): void
    {
        $this->exec(
            '`E::X` cannot override final constant `I::X`',
            'enum_interface_const_final.php',
        );
    }

    public function testInterfaceInheritanceCycleFailsWithoutRecursingForever(): void
    {
        $this->exec(
            'Interface inheritance cycle detected',
            'interface_const_inheritance_cycle.php',
        );
    }

    public function testTraitConstantMustSatisfyImplementedInterface(): void
    {
        $this->exec(
            'Declaration of `TraitConstantImplementation::VALUE` must be compatible with `TraitConstantContract::VALUE`',
            'interface_const_trait_mismatch.php',
        );
    }

    public function testTraitImportedInterfaceConstantUsesLexicalNameInArginfo(): void
    {
        global $translator;
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $translator = $compiler;
        $file = TYPEPHP_ROOT_PATH . '/phpunit/code/interface_const_trait_import.php';
        $compiler->addFiles([$file]);
        $compiler->prepareFile($file);
        $compiler->convertFile($file);

        $arginfo = file_get_contents($compiler->getArgInfoHeaderFile($file));
        self::assertIsString($arginfo);
        self::assertStringContainsString('ZVAL_LONG(&property_verbosity_default_value, 32);', $arginfo);
        self::assertStringNotContainsString('ZVAL_LONG(&property_verbosity_default_value, 999);', $arginfo);
    }
}
