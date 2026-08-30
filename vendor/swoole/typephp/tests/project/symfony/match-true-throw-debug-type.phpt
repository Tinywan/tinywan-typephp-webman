--TEST--
Symfony Config pattern: match true formats scalar values or throws by debug type
--FILE--
<?php

function displayValue(mixed $value): string|int
{
    return match (true) {
        is_int($value) => $value,
        is_string($value) => sprintf('"%s"', $value),
        is_bool($value) => throw new InvalidArgumentException(sprintf('unsupported "%s"', get_debug_type($value))),
        default => sprintf('of type "%s"', get_debug_type($value)),
    };
}

function main(): void
{
    var_dump(displayValue(7));
    var_dump(displayValue('name'));
    var_dump(displayValue([]));

    try {
        displayValue(false);
    } catch (InvalidArgumentException $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
int(7)
string(6) ""name""
string(15) "of type "array""
unsupported "bool"
