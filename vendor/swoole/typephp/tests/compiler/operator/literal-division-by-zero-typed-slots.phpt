--TEST--
Literal zero divisors on typed native slots raise catchable DivisionByZeroError
--FILE--
<?php
function divInt(int $value): mixed
{
    try {
        $value /= 0;
    } catch (DivisionByZeroError $e) {
        return $e->getMessage();
    }
    return $value;
}

function modInt(int $value): mixed
{
    try {
        $value %= 0;
    } catch (DivisionByZeroError $e) {
        return $e->getMessage();
    }
    return $value;
}

function divFloat(float $value): mixed
{
    try {
        $value /= 0.0;
    } catch (DivisionByZeroError $e) {
        return $e->getMessage();
    }
    return $value;
}

function main(): void
{
    var_dump(divInt(7));
    var_dump(modInt(7));
    var_dump(divFloat(1.5));
}
?>
--EXPECT--
string(16) "Division by zero"
string(14) "Modulo by zero"
string(16) "Division by zero"
