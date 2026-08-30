--TEST--
type hits
--FILE--
<?php
function foo(object $data) {
    var_dump($data);
}
function main()
{
    $obj = new stdClass();
    $obj->name = "John";
    $obj->age = 20;
    foo($obj);

    $arr = new ArrayObject();
    $name = "John";
    $arr["name"] = $name;
    $arr["age"] = 20;
    foo($arr);
}
?>
--EXPECT--
object(stdClass)#1 (2) {
  ["name"]=>
  string(4) "John"
  ["age"]=>
  int(20)
}
object(ArrayObject)#2 (1) {
  ["storage":"ArrayObject":private]=>
  array(2) {
    ["name"]=>
    string(4) "John"
    ["age"]=>
    int(20)
  }
}
