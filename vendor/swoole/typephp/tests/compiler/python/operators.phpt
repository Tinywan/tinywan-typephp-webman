--TEST--
Python objects use Python arithmetic, comparison, identity and truthiness protocols
--SKIPIF--
<?php
if (!extension_loaded('phpy')) {
    die('skip phpy extension is not loaded');
}
?>
--FILE--
<?php

use Python\math;

function asInt($value): int
{
    return python\scalar($value)->toInt();
}

function asFloat($value): float
{
    return python\scalar($value)->toFloat();
}

function mark(string $name, int $value): PyObject
{
    echo $name;
    return python\int($value);
}

function main(): void
{
    $seven = python\int(7);
    $three = python\int(3);

    echo asInt($seven + $three), "\n";
    echo asInt($seven - $three), "\n";
    echo asInt($seven * $three), "\n";
    echo asFloat($seven / 2), "\n";
    echo asInt($seven % $three), "\n";
    echo asInt($three ** 3), "\n";
    echo asInt($three << 2), "\n";
    echo asInt(16 >> $three), "\n";
    echo asInt($seven & $three), "\n";
    echo asInt($seven | $three), "\n";
    echo asInt($seven ^ $three), "\n";
    echo asInt(math\sqrt(9) + 1), "\n";

    // The Python reflected protocol handles the native left operand.
    echo python\scalar(python\repr(1 + python\complex(2, 3)))->toString(), "\n";

    var_dump($seven == python\int(7));
    var_dump($seven != $three);
    var_dump($seven > $three);
    var_dump($seven >= python\int(7));
    var_dump($three < $seven);
    var_dump($three <= python\int(3));

    $list = python\list([7]);
    $alias = $list;
    var_dump($list === $alias);
    var_dump($list !== python\list([7]));

    $three += 4;
    echo asInt($three), "\n";

    $compound = python\int(10);
    $compound -= 3;
    echo asInt($compound), "\n";
    $compound *= 2;
    echo asInt($compound), "\n";
    $compound /= 4;
    echo asFloat($compound), "\n";
    $compound = python\int(10);
    $compound %= 4;
    echo asInt($compound), "\n";
    $compound **= 3;
    echo asInt($compound), "\n";
    $compound <<= 2;
    echo asInt($compound), "\n";
    $compound >>= 1;
    echo asInt($compound), "\n";
    $compound &= 6;
    echo asInt($compound), "\n";
    $compound |= 1;
    echo asInt($compound), "\n";
    $compound ^= 3;
    echo asInt($compound), "\n";

    echo asInt(-$three), "\n";
    echo asInt(+$three), "\n";
    echo asInt(~$three), "\n";

    if (python\int(0)) {
        echo "bad\n";
    } else {
        echo "false\n";
    }
    if (!python\list()) {
        echo "empty\n";
    }
    var_dump(python\int(0) || python\int(1));
    var_dump(python\int(1) && python\list([1]));
    var_dump(python\int(0) xor python\list([1]));

    echo asInt(mark('L', 4) + mark('R', 5)), "\n";
}
?>
--EXPECT--
10
4
21
3.5
1
27
12
2
3
7
4
4
(3+3j)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
7
7
14
3.5
2
8
32
16
0
1
2
-7
7
-8
false
empty
bool(true)
bool(true)
bool(true)
LR9
