--TEST--
SSA narrowing: bitwise/mod on float prevents narrowing
--FILE--
<?php
function main(): void {
    // %= on float: PHP converts to int → $a stays Var
    $a = 10.5;
    $a %= 3;
    var_dump($a);

    // |= on float: PHP converts to int → $b stays Var
    $b = 6.7;
    $b |= 2;
    var_dump($b);

    // Pure float arithmetic → narrowed to Float
    $c = 3.0;
    $c += 0.14;
    $c *= 2.0;
    var_dump($c);
}
?>
--EXPECT--
int(1)
int(6)
float(6.28)
