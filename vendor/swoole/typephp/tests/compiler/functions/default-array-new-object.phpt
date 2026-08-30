--TEST--
Array parameter defaults containing new expressions resolve classes inside the helper
--FILE--
<?php

class ArrayDefaultObject
{
    public int $value = 42;
}

function readArrayDefault(array $values = [new ArrayDefaultObject()]): void
{
    var_dump($values[0]->value);
}

function main(): void
{
    readArrayDefault();
}
?>
--EXPECT--
int(42)
