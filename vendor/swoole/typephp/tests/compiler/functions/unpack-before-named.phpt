--TEST--
Argument unpacking can be followed by named arguments
--FILE--
<?php

function pair($a, $b): array
{
    return [$a, $b];
}

class DynamicUnpackNamed
{
    public function pair($a, $b): array
    {
        return [$a, $b];
    }
}

function main(): void
{
    var_dump(pair(...[1], b: 2));

    $fn = 'pair';
    var_dump($fn(...[3], b: 4));

    $object = new DynamicUnpackNamed();
    $method = 'pair';
    var_dump($object->$method(...[5], b: 6));
}
?>
--EXPECT--
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
array(2) {
  [0]=>
  int(3)
  [1]=>
  int(4)
}
array(2) {
  [0]=>
  int(5)
  [1]=>
  int(6)
}
