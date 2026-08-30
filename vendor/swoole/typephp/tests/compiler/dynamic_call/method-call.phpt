--TEST--
named args
--FILE--
<?php
class FooObject
{
    public function end($args)
    {
        var_dump($args);
    }
}

function main()
{
    $data = ['ae86', 'bmw',];
    $object = new FooObject;
    $object->end($data);
    $array[] = $object;
    $array[0]->end($data);
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(4) "ae86"
  [1]=>
  string(3) "bmw"
}
array(2) {
  [0]=>
  string(4) "ae86"
  [1]=>
  string(3) "bmw"
}