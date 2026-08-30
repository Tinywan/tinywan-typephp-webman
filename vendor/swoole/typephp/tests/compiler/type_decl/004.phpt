--TEST--
Type Declarations - Strict and weak typing modes
--FILE--
<?php
function sum_iterable($numbers) {
    $sum = 0;
    foreach ($numbers as $num) {
        $sum += $num;
    }
    return $sum;
}

function main() {
    var_dump(sum_iterable([1, 2, 3, 4, 5]));
}
?>
--EXPECT--
int(15)
