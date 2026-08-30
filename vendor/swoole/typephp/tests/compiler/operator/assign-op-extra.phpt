--TEST--
Extended assignment operators: %=, <<=, >>=, &=, |=, ^=
--FILE--
<?php

function main() {
    // Modulo assignment
    $a = 17;
    $a %= 5;
    var_dump($a);

    // Left shift assignment (Expr_AssignOp_ShiftLeft)
    $b = 1;
    $b <<= 3;
    var_dump($b);

    // Right shift assignment
    $c = 16;
    $c >>= 2;
    var_dump($c);

    // Bitwise AND assignment
    $d = 0xFF;
    $d &= 0x0F;
    var_dump($d);

    // Bitwise OR assignment
    $e = 0xF0;
    $e |= 0x0F;
    var_dump($e);

    // Bitwise XOR assignment
    $f = 0xFF;
    $f ^= 0x0F;
    var_dump($f);

    // Combined
    $x = 100;
    $x %= 7;
    $x += 10;
    var_dump($x);

    echo "done\n";
}

?>
--EXPECT--
int(2)
int(8)
int(4)
int(15)
int(255)
int(240)
int(12)
done
