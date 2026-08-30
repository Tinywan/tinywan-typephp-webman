<?php

class InheritanceReturnCovariantParentType
{
}

class InheritanceReturnCovariantChildType extends InheritanceReturnCovariantParentType
{
}

class InheritanceReturnCovariantParent
{
    public function make(): InheritanceReturnCovariantParentType
    {
        return new InheritanceReturnCovariantParentType();
    }
}

class InheritanceReturnCovariantChild extends InheritanceReturnCovariantParent
{
    public function make(): InheritanceReturnCovariantChildType
    {
        return new InheritanceReturnCovariantChildType();
    }
}
