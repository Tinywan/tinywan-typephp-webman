--TEST--
is_countable / is_iterable / method_exists / property_exists
--FILE--
<?php
class TestClass {
    public function foo() {}
    public static function bar() {}
}

class PropClass {
    public $x = 1;
    private $y = 2;
    public static $z = 3;
}

function main() {
    // is_countable
    var_dump(is_countable([]));
    var_dump(is_countable([1,2,3]));
    var_dump(is_countable("hello"));
    var_dump(is_countable(42));
    var_dump(is_countable(null));

    // is_iterable
    var_dump(is_iterable([]));
    var_dump(is_iterable([1,2,3]));
    var_dump(is_iterable("hello"));
    var_dump(is_iterable(42));
    var_dump(is_iterable(new stdClass()));

    // method_exists
    $obj = new TestClass();
    var_dump(method_exists($obj, "foo"));
    var_dump(method_exists($obj, "bar"));
    var_dump(method_exists($obj, "baz"));
    var_dump(method_exists("TestClass", "foo"));
    var_dump(method_exists("TestClass", "nonexistent"));

    // property_exists
    $p = new PropClass();
    var_dump(property_exists($p, "x"));
    var_dump(property_exists($p, "y"));
    var_dump(property_exists($p, "z"));
    var_dump(property_exists($p, "nonexistent"));
    var_dump(property_exists("PropClass", "x"));
}
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(false)
bool(false)
bool(true)
bool(true)
bool(false)
bool(false)
bool(false)
bool(true)
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(true)
bool(true)
bool(false)
bool(true)
