--TEST--
named args
--FILE--
<?php
class FooObject
{
    public function bar($args)
    {
        var_dump($args);
    }

    public function run() {
        $array['handler'] = 'bar';
        $this->{$array['handler']}([2026, 'ae86', 'bmw']);
    }
}

function main()
{
    $object = new FooObject;
    $object->run();
}
?>
--EXPECT--
array(3) {
  [0]=>
  int(2026)
  [1]=>
  string(4) "ae86"
  [2]=>
  string(3) "bmw"
}
