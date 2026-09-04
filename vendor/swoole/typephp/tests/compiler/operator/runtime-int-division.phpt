--TEST--
Runtime int division preserves PHP exact, fractional, overflow and zero-divisor semantics
--FILE--
<?php
declare(strict_types=1);

function divide(int $left, int $right): mixed
{
    return $left / $right;
}

function divide_assign(int $left, int $right): mixed
{
    $result = $left;
    $result /= $right;
    return $result;
}

function main(): void
{
    var_dump(divide(12, 3));
    var_dump(divide(10, 4));
    var_dump(divide(PHP_INT_MIN, -1));

    var_dump(divide_assign(12, 3));
    var_dump(divide_assign(10, 4));
    var_dump(divide_assign(PHP_INT_MIN, -1));

    try {
        divide(10, 0);
    } catch (DivisionByZeroError $error) {
        echo "divide by zero\n";
    }

    try {
        divide_assign(10, 0);
    } catch (DivisionByZeroError $error) {
        echo "divide assign by zero\n";
    }
}
?>
--EXPECT--
int(4)
float(2.5)
float(9.223372036854776E+18)
int(4)
float(2.5)
float(9.223372036854776E+18)
divide by zero
divide assign by zero
