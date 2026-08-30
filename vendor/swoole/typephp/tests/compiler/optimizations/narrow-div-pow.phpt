--TEST--
SSA narrowing: division and pow compound-assign prevent int narrowing
--FILE--
<?php
function main(): void {
    // /= prevents int narrowing → $a stays Var (float result in PHP)
    $a = 10;
    $a /= 3;
    var_dump($a);

    // **= prevents int narrowing → $b stays Var
    $b = 2;
    $b **= 3;
    var_dump($b);

    // *= with int RHS produces the expected runtime value,
    // but is not used as proof for native int narrowing.
    $c = 5;
    $c *= 4;
    var_dump($c);
}
?>
--EXPECT--
float(3.3333333333333335)
int(8)
int(20)
