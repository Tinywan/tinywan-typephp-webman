--TEST--
Typed int property compound assignments use PHP arithmetic and checked writes
--FILE--
<?php
declare(strict_types=1);

class IntCompoundBox
{
    public int $value = 0;
}

function intCompoundReceiver(IntCompoundBox $box, int &$calls): IntCompoundBox
{
    $calls++;
    return $box;
}

function intCompoundOperand(int $value, int &$calls): int
{
    $calls++;
    return $value;
}

function main(): void
{
    $box = new IntCompoundBox();

    $box->value = 8;
    $result = $box->value /= 2;
    var_dump($result, $box->value);

    $box->value = 8;
    try {
        $box->value /= 3;
    } catch (TypeError $e) {
        echo "fraction: ", $e::class, "\n";
    }
    var_dump($box->value);

    $box->value = PHP_INT_MAX;
    try {
        $box->value += 1;
    } catch (TypeError $e) {
        echo "add overflow: ", $e::class, "\n";
    }
    var_dump($box->value === PHP_INT_MAX);

    $box->value = PHP_INT_MAX;
    try {
        $box->value *= 2;
    } catch (TypeError $e) {
        echo "mul overflow: ", $e::class, "\n";
    }
    var_dump($box->value === PHP_INT_MAX);

    $receiverCalls = 0;
    $operandCalls = 0;
    $box->value = 5;
    $result = intCompoundReceiver($box, $receiverCalls)->value += intCompoundOperand(4, $operandCalls);
    var_dump($result, $box->value, $receiverCalls, $operandCalls);

    $receiverCalls = 0;
    $operandCalls = 0;
    $box->value = 10;
    try {
        intCompoundReceiver($box, $receiverCalls)->value /= intCompoundOperand(3, $operandCalls);
    } catch (TypeError $e) {
        echo "receiver failure: ", $e::class, "\n";
    }
    var_dump($box->value, $receiverCalls, $operandCalls);

    $box->value = 3;
    $numericString = any('4');
    $box->value += $numericString;
    var_dump($box->value);

    $box->value = 12;
    $zero = 0;
    try {
        $box->value %= $zero;
    } catch (DivisionByZeroError $e) {
        echo "modulo zero: ", $e::class, "\n";
    }
    var_dump($box->value);

    $negativeOne = -1;
    try {
        $box->value <<= $negativeOne;
    } catch (ArithmeticError $e) {
        echo "negative shift: ", $e::class, "\n";
    }
    var_dump($box->value);
}
?>
--EXPECT--
int(4)
int(4)
fraction: TypeError
int(8)
add overflow: TypeError
bool(true)
mul overflow: TypeError
bool(true)
int(9)
int(9)
int(1)
int(1)
receiver failure: TypeError
int(10)
int(1)
int(1)
int(7)
modulo zero: DivisionByZeroError
int(12)
negative shift: ArithmeticError
int(12)
