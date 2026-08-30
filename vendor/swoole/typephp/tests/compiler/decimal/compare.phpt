--TEST--
Decimal comparison and conversions
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = std::decimal("123.456");
    $b = std::decimal("789.012");

    echo "cmp(a,b)="; echo $a->cmp($b); echo "\n";
    echo "cmp(b,a)="; echo $b->cmp($a); echo "\n";
    echo "cmp(a,a)="; echo $a->cmp($a); echo "\n";

    // conversion
    echo $a->toInt(); echo "\n";
    echo $a->toString(); echo "\n";
}
?>
--EXPECT--
cmp(a,b)=-1
cmp(b,a)=1
cmp(a,a)=0
123
123.456
