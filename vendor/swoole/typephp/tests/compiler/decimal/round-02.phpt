--TEST--
Decimal: round
--FILE--
<?php
use decimal_types;

function main(): void {
    $item1 = 19.99;  // 商品A
    $item2 = 29.99;  // 商品B
    $item3 = 39.99;  // 商品C
    $tax_rate = 0.08;

    $subtotal = $item1 + $item2 + $item3;
    $tax = $subtotal * $tax_rate;
    $total = $subtotal + $tax;

    echo "小计: " . round($subtotal, 2) . "\n";        // 89.97
    echo "税额: " . round($tax, 20) . "\n";             // 7.19760000000000000000
    echo "总计: " . round($total, 2) . "\n";           // 97.17
}
?>
--EXPECT--
小计: 89.97
税额: 7.19760000000000000000
总计: 97.17
