--TEST--
named args
--FILE--
<?php
function main()
{
    include_once __DIR__.'/functions.inc';
    $rs = createUser('John', 20);
    var_dump($rs);

    $name = 'Tom';
    $rs = createUser($name, vip: true, age: 20);
    var_dump($rs);
}
?>
--EXPECT--
array(4) {
  ["name"]=>
  string(4) "John"
  ["age"]=>
  int(20)
  ["city"]=>
  string(7) "Beijing"
  ["vip"]=>
  bool(false)
}
array(4) {
  ["name"]=>
  string(3) "Tom"
  ["age"]=>
  int(20)
  ["city"]=>
  string(7) "Beijing"
  ["vip"]=>
  bool(true)
}
