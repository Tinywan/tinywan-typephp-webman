<?php

#[Attribute(Attribute::TARGET_CLASS)]
final class PreprocessorInvalidExpressionAttribute
{
    public function __construct(public array $values)
    {
    }
}

function preprocessorLoadAttributeValue(): int
{
    return 1;
}

#[PreprocessorInvalidExpressionAttribute([1, [preprocessorLoadAttributeValue()]])]
final class PreprocessorInvalidExpressionTarget
{
}
