<?php

class InheritanceParamCovariantParentType
{
}

class InheritanceParamCovariantChildType extends InheritanceParamCovariantParentType
{
}

class InheritanceParamCovariantParent
{
    public function handle(InheritanceParamCovariantParentType $value): void
    {
    }
}

class InheritanceParamCovariantChild extends InheritanceParamCovariantParent
{
    public function handle(InheritanceParamCovariantChildType $value): void
    {
    }
}
