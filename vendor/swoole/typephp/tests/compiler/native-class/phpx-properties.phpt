--TEST--
Native class: PHPX value properties retain normal string and array behavior
--FILE--
<?php

#[Native]
class NativePhpValues
{
    public string $class = 'reserved';
    public string $name = 'native';
    public array $values = [1, 2];
    public mixed $anything = null;
    public ?int $maybe = null;

    public function append(int $value): void
    {
        $this->values[] = $value;
    }
}

function main(): void
{
    $object = new NativePhpValues();
    $object->append(3);
    $object->anything = 42;
    $object->maybe = 7;
    var_dump($object->class, $object->name, $object->values, $object->anything, $object->maybe);
}

?>
--EXPECT--
string(8) "reserved"
string(6) "native"
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
int(42)
int(7)
