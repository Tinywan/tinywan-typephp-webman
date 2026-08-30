<?php

class InheritanceConstVisibilityWidenParent
{
    protected const VALUE = 1;
}

class InheritanceConstVisibilityWidenChild extends InheritanceConstVisibilityWidenParent
{
    public const VALUE = 2;
}
