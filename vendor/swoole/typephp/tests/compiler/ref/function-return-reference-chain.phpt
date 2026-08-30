--TEST--
Function returning by reference can forward another by-reference call
--FILE--
<?php

function main()
{
    $value1 = test1();
    var_dump($value1);
    $value2 = &test1();
    var_dump($value2);
    $value2 = 0;
    $value3 = test1();
    var_dump($value3);
}

function &test1()
{
    return test2();
}

function &test2()
{
    global $value;
    $value++;
    return $value;
}

// main();
?>
--EXPECT--
int(1)
int(2)
int(1)
