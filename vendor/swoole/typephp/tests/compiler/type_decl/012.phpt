--TEST--
Type Declarations
--FILE--
<?php
class SmallerTenClass {
    public static function smallerTen($input, $key) {
        return $input < 10;
    }
}

function main() {
    $array1 = [
        "a" => 1,
        "b" => 2,
        "c" => 3,
        "d" => 4,
        "e" => 5,
    ];
    var_dump(array_all($array1, "SmallerTenClass::smallerTen"));
}
?>
--EXPECT--
bool(true)