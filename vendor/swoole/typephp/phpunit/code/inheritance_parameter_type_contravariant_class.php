<?php

class InheritanceParamContravariantParentType
{
}

class InheritanceParamContravariantChildType extends InheritanceParamContravariantParentType
{
}

class InheritanceParamContravariantParent
{
    public function handle(InheritanceParamContravariantChildType $value): void
    {
    }
}

class InheritanceParamContravariantChild extends InheritanceParamContravariantParent
{
    public function handle(InheritanceParamContravariantParentType $value): void
    {
    }
}
