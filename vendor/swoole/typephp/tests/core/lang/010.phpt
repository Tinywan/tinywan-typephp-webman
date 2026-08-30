--TEST--
Testing function parameter passing with a return value
--FILE--
<?php
function test ($b) {
    $b++;
    return($b);
}
function main() {
    $a = test(1);
    echo $a;
}
?>
--EXPECT--
2
