--TEST--
GH-16649: array_splice with normal destructor should work fine
--SKIPIF--
<?php die("skip AOT does not support destructor interaction with array_splice"); ?>
--FILE--
<?php
class C {
    function __destruct() {
        echo "Destructor called\n";
    }
}

$arr = ["1", new C, "2"];
array_splice($arr, 1, 2);
var_dump($arr);
?>
--EXPECT--
Destructor called
array(1) {
  [0]=>
  string(1) "1"
}
