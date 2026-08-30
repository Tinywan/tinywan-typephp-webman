--TEST--
decimal: literal
--FILE--
<?php
use decimal_types;

function main()
{
    $a = 0.1 + 0.2;
    var_dump($a->toString());
}
?>
--EXPECT--
string(3) "0.3"