--TEST--
BigInt compound assignment operators (+=, -=, *=, /=, %=)
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // BigInt +=
    $a = std::bigInt(100);
    $a += 50;
    echo $a->toString(); echo "\n";

    // BigInt += with BigInt
    $b = std::bigInt("99999999999999999999");
    $b += std::bigInt(1);
    echo $b->toString(); echo "\n";

    // BigInt -=
    $c = std::bigInt(1000);
    $c -= 300;
    echo $c->toString(); echo "\n";

    // BigInt *=
    $d = std::bigInt(100);
    $d *= 5;
    echo $d->toString(); echo "\n";

    // BigInt /=
    $e = std::bigInt(100);
    $e /= 3;
    echo $e->toString(); echo "\n";

    // BigInt %=
    $f = std::bigInt(100);
    $f %= 7;
    echo $f->toString(); echo "\n";
}
?>
--EXPECT--
150
100000000000000000000
700
500
33
2
