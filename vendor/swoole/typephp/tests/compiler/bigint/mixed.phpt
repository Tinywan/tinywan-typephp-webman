--TEST--
BigInt mixed operations with Int
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = 12345678901234567890;
    // BigInt + Int
    $c = $a->add(100);
    echo $c->toString(); echo "\n";
    // BigInt * Int
    $d = $a->mul(2);
    echo $d->toString(); echo "\n";
    // BigInt - Int
    $e = $a->sub(7890);
    echo $e->toString(); echo "\n";
    // BigInt / Int
    $f = $a->div(10);
    echo $f->toString(); echo "\n";
    // BigInt % Int
    $g = $a->mod(1000000);
    echo $g->toString(); echo "\n";
    // BigInt cmp with Int
    echo $a->cmp(100); echo "\n";
    echo $a->cmp(99999999999999999999); echo "\n";
}
?>
--EXPECT--
12345678901234567990
24691357802469135780
12345678901234560000
1234567890123456789
567890
1
-1
