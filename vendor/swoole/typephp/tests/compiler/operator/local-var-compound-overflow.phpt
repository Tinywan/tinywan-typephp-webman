--TEST--
Dynamic local compound assignment preserves PHP integer overflow promotion
--FILE--
<?php
function main(): void {
    $add = PHP_INT_MAX;
    $add += 1;

    $mul = PHP_INT_MAX;
    $mul *= 2;

    var_dump(is_float($add));
    var_dump(is_float($mul));
}
?>
--EXPECT--
bool(true)
bool(true)
