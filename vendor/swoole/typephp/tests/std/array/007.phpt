--TEST--
array_sum / array_product: type handling
--FILE--
<?php
function main() {
    error_reporting(E_ALL & ~E_WARNING);
    // Non-numeric strings contribute 0 as int (not float)
    var_dump(array_sum([1, 2, "abc"]));
    var_dump(array_product([1, 2, "abc"]));

    // Numeric float strings produce float
    var_dump(array_sum([1, "2.5"]));
    var_dump(array_product([2, "2.5"]));

    // Pure ints produce int
    var_dump(array_sum([1, 2, 3]));
    var_dump(array_product([1, 2, 3]));

    // Actual floats produce float
    var_dump(array_sum([1, 2.5]));
    var_dump(array_product([2, 2.5]));

    // Empty array
    var_dump(array_sum([]));
    var_dump(array_product([]));
}
?>
--EXPECT--
int(3)
int(0)
float(3.5)
float(5)
int(6)
int(6)
float(3.5)
float(5)
int(0)
int(1)
