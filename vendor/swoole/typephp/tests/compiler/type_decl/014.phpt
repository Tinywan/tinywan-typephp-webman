--TEST--
Type Declarations
--FILE--
<?php
class SmallerTenClass {
    public static function foo($input) {
        var_dump(__METHOD__);
    }
}

function main() {
    $fn = "SmallerTenClass::foo";
    var_dump($fn(10));
}
?>
--EXPECT--
string(20) "SmallerTenClass::foo"
NULL