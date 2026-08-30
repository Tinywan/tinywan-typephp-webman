--TEST--
default array property
--FILE--
<?php

class Test {
    public $empty = array();
    public $three = array(1, "b"=>"c", 3=>array());

    function bar() {
        echo __METHOD__;
    }

    public $four = array('hello' => 'world', 'bar', );
}

function main() {
    $obj = new Test;
    var_dump(get_object_vars($obj));
}
?>
--EXPECT--
array(3) {
  ["empty"]=>
  array(0) {
  }
  ["three"]=>
  array(3) {
    [0]=>
    int(1)
    ["b"]=>
    string(1) "c"
    [3]=>
    array(0) {
    }
  }
  ["four"]=>
  array(2) {
    ["hello"]=>
    string(5) "world"
    [0]=>
    string(3) "bar"
  }
}
