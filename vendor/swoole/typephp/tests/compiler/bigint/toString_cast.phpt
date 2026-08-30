--TEST--
Big* types: (string) cast, strval() and echo → toString()
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    // BigInt
    $a = std::bigInt("12345678901234567890");
    var_dump((string) $a);
    var_dump(strval($a));
    echo $a; echo "\n";

    // BigFloat
    $b = std::bigFloat("3.141592653589793");
    var_dump((string) $b);
    var_dump(strval($b));
    echo $b; echo "\n";

    // Decimal
    $c = std::decimal("0.123456789");
    var_dump((string) $c);
    var_dump(strval($c));
    echo $c; echo "\n";
}
?>
--EXPECT--
string(20) "12345678901234567890"
string(20) "12345678901234567890"
12345678901234567890
string(17) "3.141592653589793"
string(17) "3.141592653589793"
3.141592653589793
string(11) "0.123456789"
string(11) "0.123456789"
0.123456789
