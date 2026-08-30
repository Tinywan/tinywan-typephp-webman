--TEST--
Symfony pattern: dynamic constant resolves enum case
--FILE--
<?php

enum SymfonyLikeFooEnum
{
    case Bar;
}

function resolveEnumConstant(string $name): UnitEnum
{
    return constant($name) instanceof UnitEnum
        ? constant($name)
        : throw new TypeError('Not an enum case.');
}

function main(): void
{
    $case = resolveEnumConstant(SymfonyLikeFooEnum::class.'::Bar');
    var_dump($case::class);
    var_dump($case->name);

    try {
        resolveEnumConstant('PHP_VERSION');
    } catch (Throwable $e) {
        var_dump($e::class);
        var_dump($e->getMessage());
    }
}
?>
--EXPECT--
string(18) "SymfonyLikeFooEnum"
string(3) "Bar"
string(9) "TypeError"
string(17) "Not an enum case."
