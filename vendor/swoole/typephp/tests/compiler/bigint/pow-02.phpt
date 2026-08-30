--TEST--
BigInt: use
--FILE--
<?php
use bigint_types;

function main()
{
    $a = 2 ** 80;
    echo $a, PHP_EOL;
}
?>
--EXPECT--
1208925819614629174706176
