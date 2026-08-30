--TEST--
Bug #35022 (Regression in the behavior of key/current functions)
--SKIPIF--
<?php die("skip AOT iteration order/internal pointer handling differs from PHP"); ?>
--FILE--
<?php
function foo(&$state)
{
    $contentDict = end($state);
    for ($contentDict = end($state); $contentDict !== false; $contentDict = prev($state)) {
        echo key($state) . " => " . current($state) . "\n";
    }
}
function main()
{
    $state = array("one" => 1, "two" => 2, "three" => 3);
    foo($state);
    reset($state);
    var_dump(key($state), current($state));
}
?>
--EXPECT--
three => 3
two => 2
one => 1
string(3) "one"
int(1)
