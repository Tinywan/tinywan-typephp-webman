--TEST--
SSA narrowing: nested integer-only ops on float prevent narrowing
--FILE--
<?php
function main(): void {
    $x = 6.7;
    $y = $x & 3;
    var_dump($y);

    $z = 10.5;
    var_dump($z % 4);
}
?>
--EXPECT--
int(2)
int(2)
