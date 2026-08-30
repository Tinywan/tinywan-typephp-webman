--TEST--
Inc/dec on bool: warning converted to exception
--FILE--
<?php

$values = [false, true];
foreach ($values as $value) {
    try {
        $value++;
    } catch (\Exception $e) {
        echo $e->getMessage(), PHP_EOL;
    }
    try {
        $value--;
    } catch (\Exception $e) {
        echo $e->getMessage(), PHP_EOL;
    }
}
?>
--EXPECTF--
Warning: Increment on type bool has no effect, this will change in the next major version of PHP in %s on line %d

Warning: Decrement on type bool has no effect, this will change in the next major version of PHP in %s on line %d

Warning: Increment on type bool has no effect, this will change in the next major version of PHP in %s on line %d

Warning: Decrement on type bool has no effect, this will change in the next major version of PHP in %s on line %d

