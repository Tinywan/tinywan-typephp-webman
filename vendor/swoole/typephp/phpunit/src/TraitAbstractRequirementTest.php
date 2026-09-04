<?php

/**
 * Trait composition drops an abstract requirement once a concrete method is
 * available for the same name. These tests cover the validation that must
 * happen before the requirement is dropped: the implementation — whether the
 * class's own method or a concrete method from another trait — has to satisfy
 * the abstract declaration under PHP's method variance rules.
 */
class TraitAbstractRequirementTest extends BaseTest
{
    public function testClassMethodMustSatisfyAbstractTraitRequirement(): void
    {
        $this->exec(
            'Declaration of `InvalidImplementation::value()` must be compatible with `RequiresValue::value()`',
            'trait_abstract_class_incompatible.php'
        );
    }

    public function testLaterConcreteTraitMethodMustSatisfyEarlierAbstract(): void
    {
        $this->exec(
            'Declaration of `HasName::name()` must be compatible with `NeedsName::name()`',
            'trait_abstract_trait_concrete_incompatible.php'
        );
    }

    public function testEarlierConcreteTraitMethodMustSatisfyLaterAbstract(): void
    {
        $this->exec(
            'Declaration of `HasName::name()` must be compatible with `NeedsName::name()`',
            'trait_abstract_trait_concrete_first_incompatible.php'
        );
    }

    public function testStaticnessMustMatchAbstractTraitRequirement(): void
    {
        $this->exec(
            'Cannot make non static method `RequiresValue::value()` static in class `StaticImplementation`',
            'trait_abstract_static_mismatch.php'
        );
    }

    public function testImplementationCannotRequireMoreParameters(): void
    {
        $this->exec(
            'Declaration of `GreedyImplementation::value()` must be compatible with `RequiresValue::value()`',
            'trait_abstract_extra_required_param.php'
        );
    }

    public function testReturnTypeCannotBeWidened(): void
    {
        $this->exec(
            'Declaration of `WideningImplementation::value()` must be compatible with `RequiresValue::value()`',
            'trait_abstract_return_widened.php'
        );
    }

    public function testAliasedAbstractRequirementIsValidatedAgainstClassMethod(): void
    {
        $this->exec(
            'Declaration of `AliasImplementation::renamed()` must be compatible with `RequiresValue::value()`',
            'trait_abstract_alias_incompatible.php'
        );
    }

    public function testValidVarianceIsAccepted(): void
    {
        // Contravariant parameters, covariant returns, extra optional
        // parameters, and visibility changes are all valid ways to fulfill
        // an abstract trait requirement.
        $this->compile('trait_abstract_variance_ok.php');
    }

    public function testExplicitTraitReturnIsNotTreatedAsSelf(): void
    {
        $this->exec(
            'Declaration of `InvalidExplicitTraitReturn::make()` must be compatible with `ExplicitTraitReturnRequirement::make()`',
            'trait_abstract_explicit_trait_return.php'
        );
    }

    public function testExplicitTraitTypeInUnionIsNotTreatedAsSelf(): void
    {
        $this->exec(
            'Declaration of `InvalidExplicitTraitUnionReturn::make()` must be compatible with `ExplicitTraitUnionRequirement::make()`',
            'trait_abstract_explicit_trait_union_return.php'
        );
    }

    public function testExplicitTraitParameterIsNotTreatedAsSelf(): void
    {
        $this->exec(
            'Declaration of `InvalidExplicitTraitParameter::accept()` must be compatible with `ExplicitTraitParameterRequirement::accept()`',
            'trait_abstract_explicit_trait_parameter.php'
        );
    }

    public function testParentReturnUsesTheConsumingClassParent(): void
    {
        $this->exec(
            'Declaration of `InvalidParentReturnImplementation::make()` must be compatible with `ParentReturnRequirement::make()`',
            'trait_abstract_parent_return.php'
        );
    }

    public function testParentUnionParameterUsesTheConsumingClassParent(): void
    {
        $this->exec(
            'Declaration of `InvalidParentParamImplementation::accept()` must be compatible with `ParentParamRequirement::accept()`',
            'trait_abstract_parent_union_param.php'
        );
    }

    public function testNestedTraitCannotResolveParentFromAnEventualConsumer(): void
    {
        $this->exec(
            'Cannot use "parent" when current class scope has no parent',
            'trait_abstract_nested_parent_unavailable.php'
        );
    }
}
