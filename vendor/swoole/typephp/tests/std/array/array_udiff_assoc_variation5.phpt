--TEST--
Test array_udiff_assoc() function : usage variation - incorrect comparison functions
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
    echo "*** Testing array_udiff_assoc() : usage variation - differing comparison functions***\n";
    $arr1 = array(1);
    $arr2 = array(1, 2);
    echo "\n-- comparison function with an incorrect return value --\n";
    var_dump(array_udiff_assoc($arr1, $arr2, 'incorrect_return_value'));
    echo "\n-- comparison function taking too many parameters --\n";
    try {
        var_dump(array_udiff_assoc($arr1, $arr2, 'too_many_parameters'));
    } catch (Throwable $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
    echo "\n-- comparison function taking too few parameters --\n";
    var_dump(array_udiff_assoc($arr1, $arr2, 'too_few_parameters'));
}
?>
--EXPECT--
*** Testing array_udiff_assoc() : usage variation - differing comparison functions***

-- comparison function with an incorrect return value --
array(1) {
  [0]=>
  int(1)
}

-- comparison function taking too many parameters --
array(1) {
  [0]=>
  int(1)
}

-- comparison function taking too few parameters --
array(1) {
  [0]=>
  int(1)
}
