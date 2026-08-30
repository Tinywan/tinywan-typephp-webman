--TEST--
BigInt: use
--FILE--
<?php
use bigint_types;

function main()
{
    require __DIR__ . '/../../../src/Assert.php';
    $a = 3;
    $b = $a->pow(80);
    var_dump($b->toString());

    $c = $a ** 80;
    var_dump($b->toString());
}
?>
--EXPECT--
string(39) "147808829414345923316083210206383297601"
string(39) "147808829414345923316083210206383297601"
