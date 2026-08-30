--TEST--
Keyword MethodsFor method with lowerCamelCase name
--FILE--
<?php

declare(strict_types=1);
use native_types;

#[MethodsFor('*')]
final class KeywordExtensions
{
    public static function inspectValue(mixed $value, string $prefix): void
    {
        echo $prefix, ':', $value, "\n";
    }
}

#[MethodsFor(Type::Any)]
final class AnyExtensions
{
    public static function dynamicType(mixed $value): string
    {
        return get_debug_type($value);
    }
}

function main(): void
{
    $value = 42;
    $value->inspectValue('number');

    $dynamic = $value->toAny();
    echo $dynamic->dynamicType(), "\n";
}
?>
--EXPECT--
number:42
int
