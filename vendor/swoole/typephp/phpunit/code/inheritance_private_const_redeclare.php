<?php

class InheritancePrivateConstRedeclareParent
{
    private const int VALUE = 1;
}

class InheritancePrivateConstRedeclareChild extends InheritancePrivateConstRedeclareParent
{
    public const string VALUE = 'child';
}
