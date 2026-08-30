--TEST--
type hits
--FILE--
<?php
function foo(ArrayAccess $data) {
    var_dump($data);
}
function main()
{
    $arr = new ArrayObject();
    $name = "John";
    $arr["name"] = $name;
    $arr["age"] = 20;
    foo($arr);
}
?>
--EXPECT--
object(ArrayObject)#1 (1) {
  ["storage":"ArrayObject":private]=>
  array(2) {
    ["name"]=>
    string(4) "John"
    ["age"]=>
    int(20)
  }
}
