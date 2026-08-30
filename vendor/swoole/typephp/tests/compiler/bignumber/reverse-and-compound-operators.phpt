--TEST--
Big numeric reverse non-commutative and BigFloat compound operators
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $bi = std::bigInt(4);
    echo (10 - $bi)->toString(), "\n";
    echo (10 / $bi)->toString(), "\n";
    echo (10 % $bi)->toString(), "\n";
    echo (std::bigInt(-7) / 3)->toString(), "\n";
    echo (std::bigInt(-7) % 3)->toString(), "\n";

    $dec = std::decimal("4.0");
    echo (10 - $dec)->toString(), "\n";
    echo (10 / $dec)->toString(), "\n";
    echo (10 % $dec)->toString(), "\n";

    $bf = std::bigFloat("4.0");
    echo (10 - $bf)->toString(), "\n";
    echo (10 / $bf)->toString(), "\n";
    $bf += 2;
    $bf -= 1;
    $bf *= 3;
    $bf /= 5;
    echo $bf->toString(), "\n";
}
?>
--EXPECT--
6
2
2
-2
-1
6.0
2.5
2.0
6
2.5
3
