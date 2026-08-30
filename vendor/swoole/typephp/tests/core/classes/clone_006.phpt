--TEST--
ZE2 object cloning, 6
--INI--
error_reporting=2047
--FILE--
<?php

class MyCloneable {
    static $nextId = 0;
    public $id;

    function __construct() {
        $this->id = self::$nextId++;
    }

    function __clone() {
        $this->address = "New York";
        $this->id = self::$nextId++;
    }
}
function main() {
    $original = new MyCloneable();

    $original->name = "Hello";
    $original->address = "Tel-Aviv";

    echo $original->id . "\n";

    $clone = clone $original;

    echo $clone->id . "\n";
    echo $clone->name . "\n";
    echo $clone->address . "\n";
}
?>
--EXPECTF--
0
1
Hello
New York
