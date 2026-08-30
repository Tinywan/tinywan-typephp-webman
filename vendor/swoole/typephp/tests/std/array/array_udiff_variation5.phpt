--TEST--
Test array_udiff() function : usage variation
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
    return 0;
}
function too_few_parameters($val1, $val2)
{
    return 0;
}
function main()
{
    echo "*** Testing array_udiff() : usage variation ***\n";
    // Initialise function arguments not being substituted (if any)
    $arr1 = array(1);
    $arr2 = array(1);
    echo "\n-- comparison function with an incorrect return value --\n";
    var_dump(array_udiff($arr1, $arr2, 'incorrect_return_value'));
    echo "\n-- comparison function taking too many parameters --\n";
    try {
        var_dump(array_udiff($arr1, $arr2, 'too_many_parameters'));
    } catch (Throwable $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
    echo "\n-- comparison function taking too few parameters --\n";
    var_dump(array_udiff($arr1, $arr2, 'too_few_parameters'));
}
?>
--EXPECT--
*** Testing array_udiff() : usage variation ***

-- comparison function with an incorrect return value --
array(1) {
  [0]=>
  int(1)
}

-- comparison function taking too many parameters --
array(0) {
}

-- comparison function taking too few parameters --
array(0) {
}
