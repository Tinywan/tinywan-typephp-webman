--TEST--
Constant and variable with the same name (case-insensitive) resolve independently
--FILE--
<?php
const a = 123;

function main(): void
{
    $a = 456;
    var_dump(a, $a);
}
?>
--EXPECT--
int(123)
int(456)
