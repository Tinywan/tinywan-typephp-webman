<?php

function loadClassConstantValue(): int
{
    return 1;
}

class ClassConstantInvalidExpression
{
    public const VALUE = loadClassConstantValue();
}
