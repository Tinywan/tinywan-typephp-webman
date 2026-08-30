--TEST--
unset on arrays and variables
--FILE--
<?php

class Bar {
    public $object;
}

function foo($obj) {
    $obj = new ArrayObject();
    $obj['hello'] = 'world';
}

function main() {
    $bar = new Bar();
    $o1 = new stdClass();
    $o1->a = 1;
    $o1->b = 2;
    $o1->c = 3;
    $bar->object = $o1;
    foo($bar->object);
    var_dump($bar->object);

    $arr = ['object' => $o1];
    foo($arr['object']);
    var_dump($arr['object']);
}
?>
--EXPECTF--
object(stdClass)#%d (3) {
  ["a"]=>
  int(1)
  ["b"]=>
  int(2)
  ["c"]=>
  int(3)
}
object(stdClass)#%d (3) {
  ["a"]=>
  int(1)
  ["b"]=>
  int(2)
  ["c"]=>
  int(3)
}
