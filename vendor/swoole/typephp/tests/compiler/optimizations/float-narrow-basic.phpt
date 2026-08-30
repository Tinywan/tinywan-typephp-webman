--TEST--
SSA narrowing: basic float operations
--FILE--
<?php
function main(): void {
    // 1.0 + 0.5 = 1.5, * 2 = 3.0 (all dyadic fractions, no precision loss)
    $a = 1.0;
    $a += 0.5;
    $a *= 2.0;
    var_dump($a);

    // 10.0 - 3.5 = 6.5 (dyadic)
    $b = 10.0;
    $b -= 3.5;
    var_dump($b);

    // 2.5 + 1 = 3.5 (dyadic)
    $c = 2.5;
    $c++;
    var_dump($c);
}
?>
--EXPECT--
float(3)
float(6.5)
float(3.5)
