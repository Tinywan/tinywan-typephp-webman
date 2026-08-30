--TEST--
Embedded NUL bytes in string literals
--FILE--
<?php

function main(): void
{
    $value = "hello \0 world";

    var_dump(strlen($value));
    var_dump(bin2hex($value));
    var_dump($value === "hello \0 world");
    var_dump($value . "!" === "hello \0 world!");
    var_dump(eval('return "A\\0B";') === "A\0B");
}
?>
--EXPECT--
int(13)
string(26) "68656c6c6f200020776f726c64"
bool(true)
bool(true)
bool(true)
