--TEST--
array_map() and exceptions in the callback
--SKIPIF--
<?php die("skip AOT array_map with exceptions differs from PHP"); ?>
--FILE--
<?php
function foo()
{
    throw new exception(1);
}
function main()
{
    $a = array(1, 2, 3);
    try {
        array_map("foo", $a, array(2, 3));
    } catch (Exception $e) {
        var_dump("exception caught!");
    }
    echo "Done\n";
}
?>
--EXPECT--
string(17) "exception caught!"
Done
