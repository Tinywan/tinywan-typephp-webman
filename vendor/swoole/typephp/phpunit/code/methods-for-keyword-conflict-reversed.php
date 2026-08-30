<?php

#[MethodsFor(Type::String)]
class ReversedConflictingStringMethods
{
    public static function inspect(string $value): string
    {
        return $value;
    }
}

#[MethodsFor('*')]
class ReversedConflictingKeywordMethods
{
    public static function inspect(any $value): string
    {
        return 'keyword';
    }
}

function methods_for_reversed_keyword_conflict(string $value): string
{
    return $value->inspect();
}
