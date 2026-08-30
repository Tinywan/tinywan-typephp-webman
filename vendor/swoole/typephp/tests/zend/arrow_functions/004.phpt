--TEST--
Auto-globals in arrow functions
--FILE--
<?php
function main() {
    global $a;
    $a = 123;
    $fn = fn() => $GLOBALS['a'];
    var_dump($fn());
}
?>
--EXPECT--
int(123)
