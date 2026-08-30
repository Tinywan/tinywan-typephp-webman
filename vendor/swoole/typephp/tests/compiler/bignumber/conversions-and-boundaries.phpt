--TEST--
Big numeric casts, conversion functions, and runtime boundaries
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $bigint = std::bigInt("42");
    var_dump((int) $bigint, (float) $bigint, (bool) $bigint);
    var_dump(intval($bigint), floatval($bigint), boolval(std::bigInt("0")));

    $decimal = std::decimal("-12.75");
    var_dump((int) $decimal, (float) $decimal, (bool) $decimal, boolval(std::decimal("0.00")));

    $bigfloat = std::bigFloat("3.75");
    var_dump((int) $bigfloat, (float) $bigfloat, (bool) $bigfloat, boolval(std::bigFloat("0")));

    try {
        $bigintRangeValue = (int) std::bigInt("9223372036854775808");
    } catch (ArithmeticError $e) {
        echo "bigint range caught\n";
    }

    try {
        $invalidBigint = std::bigInt("not-an-integer");
    } catch (ValueError $e) {
        echo "invalid bigint caught\n";
    }

    try {
        $bigfloatDivision = std::bigFloat("1") / 0;
    } catch (DivisionByZeroError $e) {
        echo "bigfloat division caught\n";
    }

    try {
        $bigfloatRangeValue = (int) std::bigFloat("1e100");
    } catch (ArithmeticError $e) {
        echo "bigfloat range caught\n";
    }

    echo std::bigFloat("1e1000001")->toString(), "\n";
}
?>
--EXPECT--
int(42)
float(42)
bool(true)
int(42)
float(42)
bool(false)
int(-12)
float(-12.75)
bool(true)
bool(false)
int(3)
float(3.75)
bool(true)
bool(false)
bigint range caught
invalid bigint caught
bigfloat division caught
bigfloat range caught
1E1000001
