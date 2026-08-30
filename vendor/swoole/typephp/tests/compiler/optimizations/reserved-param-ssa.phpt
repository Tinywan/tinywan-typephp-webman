--TEST--
SSA optimizations handle parameters with C++ reserved names
--FILE--
<?php

function sum_reserved(int $union, int $class): int
{
    $total = 0;
    for ($i = 0; $i < $union; $i++) {
        $total += $class;
    }
    return $total;
}

function main(): void
{
    var_dump(sum_reserved(union: 4, class: 3));
}
?>
--EXPECT--
int(12)
