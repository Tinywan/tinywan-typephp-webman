--TEST--
Test array_uintersect_uassoc() function : usage variation - incorrect callbacks
--SKIPIF--
<?php //die("skip AOT does not support string-based callbacks"); ?>

--FILE--
<?php
function incorrect_return_value($val1, $val2)
{
    return array(1);
}
function too_many_parameters($val1, $val2)
{
    return 1;
}
function too_few_parameters($val1, $val2)
{
    return 1;
}
function main()
{
    echo "*** Testing array_uintersect_uassoc() : usage variation - incorrect callbacks ***\n";
    $arr1 = array(1);
    $arr2 = array(1, 2);
    echo "\n-- comparison function with an incorrect return value --\n";
    var_dump(array_uintersect_uassoc($arr1, $arr2, 'incorrect_return_value', 'incorrect_return_value'));
    echo "\n-- comparison function taking too many parameters --\n";
    try {
        var_dump(array_uintersect_uassoc($arr1, $arr2, 'too_many_parameters', 'too_many_parameters'));
    } catch (Throwable $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
    echo "\n-- comparison function taking too few parameters --\n";
    var_dump(array_uintersect_uassoc($arr1, $arr2, 'too_few_parameters', 'too_few_parameters'));
}
?>
--EXPECT--
*** Testing array_uintersect_uassoc() : usage variation - incorrect callbacks ***

-- comparison function with an incorrect return value --
array(0) {
}

-- comparison function taking too many parameters --
array(0) {
}

-- comparison function taking too few parameters --
array(0) {
}
