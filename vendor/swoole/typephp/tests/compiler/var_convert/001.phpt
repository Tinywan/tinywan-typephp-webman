--TEST--
var convert chained on array element
--FILE--
<?php
use native_types;
function main()
{
    $arr = [];
    $name = "hello";
    $arr["name"] = $name;
    $arr["age"] = 20;

    var_dump($arr["age"]->toString());
    var_dump($arr["name"]->toString());
}
?>
--EXPECT--
string(2) "20"
string(5) "hello"
