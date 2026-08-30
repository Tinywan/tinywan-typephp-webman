--TEST--
Big numeric operator error boundaries use PHP-compatible exception types
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    try {
        $unused = std::bigInt(1) / 0;
    } catch (DivisionByZeroError $e) {
        echo "bigint division by zero\n";
    }

    try {
        $unused = std::bigInt(1) % 0;
    } catch (DivisionByZeroError $e) {
        echo "bigint modulo by zero\n";
    }

    try {
        $unused = std::bigInt(2) ** -1;
    } catch (TypeError $e) {
        echo "bigint negative exponent\n";
    }

    try {
        $unused = std::decimal("1") % 0;
    } catch (DivisionByZeroError $e) {
        echo "decimal modulo by zero\n";
    }

    try {
        $unused = std::decimal("NaN") <=> std::decimal("1");
    } catch (ArithmeticError $e) {
        echo "decimal NaN comparison\n";
    }

    try {
        $unused = std::bigFloat("NAN") <=> std::bigFloat("1");
    } catch (ArithmeticError $e) {
        echo "bigfloat NaN comparison\n";
    }
}
?>
--EXPECT--
bigint division by zero
bigint modulo by zero
bigint negative exponent
decimal modulo by zero
decimal NaN comparison
bigfloat NaN comparison
