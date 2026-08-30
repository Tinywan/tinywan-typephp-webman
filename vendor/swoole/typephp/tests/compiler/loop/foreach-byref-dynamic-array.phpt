--TEST--
foreach by reference supports dynamically evaluated arrays
--FILE--
<?php

function values(): array
{
    return [1, 2, 3];
}

function main(): void
{
    foreach (values() as &$value) {
        $value *= 2;
        var_dump($value);
    }
}
?>
--EXPECT--
int(2)
int(4)
int(6)
