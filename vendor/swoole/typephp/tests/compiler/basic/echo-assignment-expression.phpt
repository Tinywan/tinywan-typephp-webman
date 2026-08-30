--TEST--
echo accepts assignment expressions
--FILE--
<?php

function main(): void
{
    echo $first = 'first', ':', $second = 2, "\n";
    var_dump($first, $second);
}
?>
--EXPECT--
first:2
string(5) "first"
int(2)
