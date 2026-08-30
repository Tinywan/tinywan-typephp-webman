--TEST--
pow int overflow
--FILE--
<?php
function main()
{
    $a = 2 ** 80;
    echo $a, PHP_EOL;
}
?>
--EXPECT--
1.2089258196146292E+24