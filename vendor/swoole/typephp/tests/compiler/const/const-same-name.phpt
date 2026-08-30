--TEST--
Constant with the same name (case-insensitive) resolve independently
--FILE--
<?php
const a = 123;
const A = 456;

function main(): void
{
    var_dump(a, A);
}
?>
--EXPECT--
int(123)
int(456)
