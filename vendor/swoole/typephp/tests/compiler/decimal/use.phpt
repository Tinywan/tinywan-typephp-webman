--TEST--
decimal: literal
--FILE--
<?php
use decimal_types;

function main()
{
    require __DIR__ . '/../../../src/Assert.php';
    $a = 3.1;
    $b = $a + 1.2;
    var_dump($b->toString());

    $c = 0.0;
    $d = ($c + 3.1) * 5.331;
    var_dump($d->toString());
}
?>
--EXPECT--
string(3) "4.3"
string(7) "16.5261"