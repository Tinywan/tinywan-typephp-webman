--TEST--
trait method writes injected private property on child object
--SKIPIF--
--FILE--
<?php

trait PrivatePropertyWriter
{
    private ?array $items = null;

    public function items(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }
        return $this->items = [1, 3, 4];
    }
}

class PropertyOwner
{
    use PrivatePropertyWriter;
}

class ChildPropertyOwner extends PropertyOwner
{
}

function main(): void
{
    $object = new ChildPropertyOwner();
    var_dump($object->items());
}

?>
--EXPECT--
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(3)
  [2]=>
  int(4)
}
