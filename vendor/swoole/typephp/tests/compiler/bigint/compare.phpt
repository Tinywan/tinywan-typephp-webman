--TEST--
BigInt comparison
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = 12345678901234567890;
    $b = 98765432109876543210;
    // cmp returns -1 (a<b), 0 (a==b), 1 (a>b)
    echo "cmp(a,b)="; echo $a->cmp($b); echo "\n";
    echo "cmp(b,a)="; echo $b->cmp($a); echo "\n";
    echo "cmp(a,a)="; echo $a->cmp($a); echo "\n";
}
?>
--EXPECT--
cmp(a,b)=-1
cmp(b,a)=1
cmp(a,a)=0
