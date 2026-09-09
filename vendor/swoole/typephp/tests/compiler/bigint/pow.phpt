--TEST--
BigInt: pow
--FILE--
<?php
function main()
{
    require __DIR__ . '/../../../src/Assert.php';
    $a = 3;
    $b = $a->pow(3);
    Assert::eq($b, 27);

    $d = std::any(5);
    $c = $a->pow($d);
    Assert::eq($c, 243);
}
?>
--EXPECT--
