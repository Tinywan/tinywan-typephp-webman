--TEST--
decimal: literal
--FILE--
<?php
use decimal_types;

function main()
{
    require __DIR__ . '/../../../src/Assert.php';
    $amount = 19.99;
    $rate = 0.09;
    $tax = $amount * $rate;  // 1.7991 —— Decimal 精确计算
    echo floor($tax * 100) / 100.0;  // floor(179.91) / 100.0 = 1.79
}
?>
--EXPECT--
1.79