--TEST--
Decimal unary minus operator
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = std::decimal("123.456");

    // Unary minus
    $b = -$a;
    echo $b->toString(); echo "\n";
    // Double minus
    $c = -$b;
    echo $c->toString(); echo "\n";
}
?>
--EXPECT--
-123.456
123.456
