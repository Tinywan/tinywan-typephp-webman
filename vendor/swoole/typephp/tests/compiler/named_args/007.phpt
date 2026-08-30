--TEST--
Named arguments and promoted properties with C++ reserved parameter names
--FILE--
<?php

function reserved_params($union, $class = 2): array
{
    return [$union, $class];
}

class ReservedPromotion
{
    public function __construct(
        public int $union,
        public string $class = "default"
    ) {
    }
}

function main(): void
{
    var_dump(reserved_params(class: 20, union: 10));

    $object = new ReservedPromotion(class: "named", union: 42);
    var_dump($object->union);
    var_dump($object->class);
}
?>
--EXPECT--
array(2) {
  [0]=>
  int(10)
  [1]=>
  int(20)
}
int(42)
string(5) "named"
