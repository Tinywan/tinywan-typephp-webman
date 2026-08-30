<?php

#[MethodsFor('*')]
class ConflictingKeywordMethods
{
    public static function inspect(any $value): string
    {
        return 'keyword';
    }
}

#[MethodsFor(Type::String)]
class ConflictingStringMethods
{
    public static function inspect(string $value): string
    {
        return $value;
    }
}

function methods_for_keyword_conflict(string $value): string
{
    return $value->inspect();
}
