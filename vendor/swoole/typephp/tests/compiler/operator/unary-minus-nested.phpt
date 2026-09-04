--TEST--
Nested unary minus must not emit the C++ pre-decrement token
--FILE--
<?php

function negNative(int $x): int
{
    return - -$x;
}

function main(): void
{
    $a = 5;
    $b = - -$a;
    echo $b, "\n";
    echo $a, "\n";

    $c = -(-7);
    echo $c, "\n";

    $f = 1.5;
    $g = - -$f;
    echo $g, "\n";

    echo negNative(9), "\n";
    echo - -(-3), "\n";
}
?>
--EXPECT--
5
5
7
1.5
9
-3
