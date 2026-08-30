--TEST--
BigInt exponentiation dispatches when BigInt is the right operand
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $exponent = std::bigInt("10");
    echo (2 ** $exponent)->toString(), "\n";
}
?>
--EXPECT--
1024
