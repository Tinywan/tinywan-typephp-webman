--TEST--
GH-16649: array_splice UAF when array is converted from packed to hash
--SKIPIF--
<?php die("skip AOT does not support destructor interaction with array_splice"); ?>
--FILE--
<?php
class C {
    function __destruct() {
        global $arr;
        // array is converted from packed to hash
        $arr["str"] = 0;
    }
}

$arr = ["1", new C, "2"];

try {
    array_splice($arr, 1, 2);
    echo "ERROR: Should have thrown exception\n";
} catch (Error $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
}
?>
--EXPECT--
Exception caught: Array was modified during array_splice operation
