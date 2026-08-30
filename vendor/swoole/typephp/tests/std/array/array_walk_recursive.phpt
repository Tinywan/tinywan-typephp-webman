--TEST--
Test array_walk_recursive()
--SKIPIF--
--FILE--
<?php
function foo($value, $key)
{
    echo $value . " foo\n";
}
function bar($value, $key)
{
    echo $value . " bar\n";
}
function main()
{
    $arr = array(1, 2, 3);
    var_dump(array_walk_recursive($arr, 'foo'));
    var_dump(array_walk_recursive($arr, 'bar'));
}
?>
--EXPECT--
1 foo
2 foo
3 foo
bool(true)
1 bar
2 bar
3 bar
bool(true)
