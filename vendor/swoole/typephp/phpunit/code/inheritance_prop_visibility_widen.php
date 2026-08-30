<?php

class InheritancePropVisibilityWidenParent
{
    protected int $value = 1;
}

class InheritancePropVisibilityWidenChild extends InheritancePropVisibilityWidenParent
{
    public int $value = 2;
}
