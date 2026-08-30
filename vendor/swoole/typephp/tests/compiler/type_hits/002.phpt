--TEST--
type hits
--FILE--
<?php
function foo(stdClass $data) {
    var_dump($data);
}

function main()
{
    $obj = new stdClass();
    $obj->name = "John";
    $obj->age = 20;
    foo($obj);
}
?>
--EXPECT--
object(stdClass)#1 (2) {
  ["name"]=>
  string(4) "John"
  ["age"]=>
  int(20)
}
