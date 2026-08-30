--TEST--
Symfony Routing pattern: enum cases, class coalesce assign, dynamic instanceof
--FILE--
<?php

enum Locale: string
{
    case EN = 'en';
    case FR = 'fr';
}

enum Status: string
{
    case Draft = 'draft';
}

function enum_requirement(array $cases): string
{
    $class = null;
    foreach ($cases as $case) {
        if (!$case instanceof BackedEnum) {
            return 'invalid type';
        }

        $class ??= $case::class;

        if (!$case instanceof $class) {
            return sprintf('%s::%s not in %s', get_debug_type($case), $case->name, $class);
        }
    }

    return implode('|', array_map(static fn ($e) => preg_quote($e->value), $cases));
}

function main(): void
{
    var_dump(enum_requirement([Locale::EN, Locale::FR]));
    var_dump(enum_requirement([Locale::EN, Status::Draft]));
}
?>
--EXPECT--
string(5) "en|fr"
string(27) "Status::Draft not in Locale"
