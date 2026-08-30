<?php

class InheritanceReturnContravariantParentType
{
}

class InheritanceReturnContravariantChildType extends InheritanceReturnContravariantParentType
{
}

class InheritanceReturnContravariantParent
{
    public function make(): InheritanceReturnContravariantChildType
    {
        return new InheritanceReturnContravariantChildType();
    }
}

class InheritanceReturnContravariantChild extends InheritanceReturnContravariantParent
{
    public function make(): InheritanceReturnContravariantParentType
    {
        return new InheritanceReturnContravariantParentType();
    }
}
