<?php

class FinalConstantParent
{
    final public const VALUE = 1;
}

class FinalConstantChild extends FinalConstantParent
{
    public const VALUE = 2;
}

function main() {}
