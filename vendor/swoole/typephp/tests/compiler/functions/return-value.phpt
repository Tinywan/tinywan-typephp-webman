--TEST--
object link operator
--FILE--
<?php
function var_dump_test()
{
    var_dump('foo');
    return;
}

function main()
{
    $a = var_dump_test();
    var_dump($a);
}
?>
--EXPECT--
string(3) "foo"
NULL