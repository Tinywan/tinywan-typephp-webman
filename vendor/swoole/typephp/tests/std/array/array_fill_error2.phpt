--TEST--
Test array_fill() function : error conditions - count is too large
--SKIPIF--
--FILE--
<?php
$intMax = 2147483647;

// calling array_fill() with 'count' larger than INT_MAX
try {
    $array = array_fill(0, $intMax+1, 1);
} catch (\ValueError $e) {
    echo $e->getMessage() . "\n";
}

// calling array_fill() with 'count' equals to INT_MAX
$array = array_fill(0, $intMax, 1);

?>
--EXPECTF--
%SFatal error: Possible integer overflow in memory allocation (%d * %d + %d) in %s
