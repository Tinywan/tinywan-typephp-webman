<?php

class TypedConstantParent
{
    public const int VALUE = 1;
}

class UntypedConstantChild extends TypedConstantParent
{
    public const VALUE = 1;
}

function main() {}
