<?php

/**
 * Constructor promotion and property/constant type restrictions:
 * no variadic promoted properties, `callable` is banned from
 * property and class-constant types (bare, nullable, or union member),
 * and `callable` inside an intersection or DNF member is rejected in
 * every declaration context - parameters, returns, properties, promoted
 * properties, constants, interface members, and closures.
 */
class PromotionAndPropertyTypeTest extends BaseTest
{
    public function testVariadicPromotedPropertyIsRejected(): void
    {
        $this->exec('Cannot declare variadic promoted property', 'promotion_rule_variadic.php');
    }

    public function testCallablePropertyTypeIsRejected(): void
    {
        $this->exec('Property `Bag::$fn` cannot have type `callable`', 'property_rule_callable.php');
    }

    public function testCallablePromotedPropertyTypeIsRejected(): void
    {
        $this->exec('Property `Bag::$fn` cannot have type `callable`', 'property_rule_callable_promoted.php');
    }

    public function testCallableUnionPropertyTypeIsRejected(): void
    {
        $this->exec('Property `Bag::$fn` cannot have type `int|callable`', 'property_rule_callable_union.php');
    }

    public function testCallableClassConstantTypeIsRejected(): void
    {
        $this->exec('Class constant `Bag::FN` cannot have type `callable`', 'const_rule_callable.php');
    }

    public function testCallableInBareIntersectionIsRejected(): void
    {
        // Zend rejects callable while compiling the intersection type itself,
        // with a dedicated diagnostic; without this check the type reaches
        // gen_stub, which asserts intersection members are never builtin.
        $this->exec('Type callable cannot be part of an intersection type', 'property_rule_callable_intersection.php');
    }

    public function testCallableInDnfPropertyTypeIsRejected(): void
    {
        $this->exec('Type callable cannot be part of an intersection type', 'property_rule_callable_dnf.php');
    }

    public function testCallableInSecondDnfMemberIsRejected(): void
    {
        $this->exec('Type callable cannot be part of an intersection type', 'property_rule_callable_dnf_second_member.php');
    }

    public function testCallableInDnfPromotedPropertyTypeIsRejected(): void
    {
        $this->exec('Type callable cannot be part of an intersection type', 'property_rule_callable_dnf_promoted.php');
    }

    public function testCallableInDnfClassConstantTypeIsRejected(): void
    {
        $this->exec('Type callable cannot be part of an intersection type', 'const_rule_callable_dnf.php');
    }

    public function testCallableInDnfInterfaceMemberTypesIsRejected(): void
    {
        $this->exec('Type callable cannot be part of an intersection type', 'interface_rule_callable_dnf.php');
    }

    public function testCallableInIntersectionParameterTypeIsRejected(): void
    {
        $this->exec('Type callable cannot be part of an intersection type', 'param_rule_callable_intersection.php');
    }

    public function testCallableInDnfParameterTypeIsRejected(): void
    {
        $this->exec('Type callable cannot be part of an intersection type', 'param_rule_callable_dnf.php');
    }

    public function testCallableInIntersectionReturnTypeIsRejected(): void
    {
        $this->exec('Type callable cannot be part of an intersection type', 'return_rule_callable_intersection.php');
    }

    public function testCallableInIntersectionClosureParameterTypeIsRejected(): void
    {
        $this->exec('Type callable cannot be part of an intersection type', 'closure_rule_callable_intersection_param.php');
    }

    public function testCallableInIntersectionClosureReturnTypeIsRejected(): void
    {
        $this->exec('Type callable cannot be part of an intersection type', 'closure_rule_callable_intersection_return.php');
    }

    public function testCallableFreeDnfPropertyTypeStillCompiles(): void
    {
        $this->compile('property_rule_dnf_valid.php');
    }

    public function testBareCallableParameterTypeStillCompiles(): void
    {
        $this->compile('param_rule_callable_valid.php');
    }
}
