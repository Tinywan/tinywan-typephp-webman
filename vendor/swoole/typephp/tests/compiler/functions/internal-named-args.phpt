--TEST--
Internal function named arguments are validated before optimization
--FILE--
<?php

function main(): void
{
    var_dump(strlen(string: "abc"));
    var_dump(str_replace(replace: "x", subject: "abc", search: "a"));
    var_dump(substr(length: 2, string: "abcdef", offset: 3));
}
?>
--EXPECT--
int(3)
string(3) "xbc"
string(2) "de"
