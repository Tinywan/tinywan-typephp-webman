--TEST--
sum
--FILE--
<?php
// Test basic variadic function
function sum(...$numbers): int {
    return array_sum($numbers);
}

function test_unpacking() {
    $numbers = [1, 2, 3, 4];
    var_dump(sum(...$numbers));

    $args = [10, 20, 30, 40, 50];
    var_dump(sum(...$args));

    // Unpack multiple arrays
    $arr1 = [1, 2];
    $arr2 = [3, 4];
    var_dump(sum(...$arr1, ...$arr2));
}

function main()
{
    var_dump(sum(1, 2, 3, 4, 5));
    var_dump(sum(10, 20, 30));
    var_dump(sum());

    // Test unpacking arrays into variadic functions
    test_unpacking();
}
?>
--EXPECT--
int(15)
int(60)
int(0)
int(10)
int(150)
int(10)
