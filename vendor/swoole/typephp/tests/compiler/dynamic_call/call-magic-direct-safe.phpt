--TEST--
compiled __call fast path preserves arguments and dynamic subclass dispatch
--FILE--
<?php
class DirectMagic
{
    public function __call(string $name, array $arguments): mixed
    {
        return [$name, $arguments];
    }
}

class InheritedMagic extends DirectMagic
{
}

class RuntimeMethod extends DirectMagic
{
    public function existing(): string
    {
        return 'runtime-method';
    }
}

function callFromDeclaredBase(DirectMagic $object): mixed
{
    return $object->existing();
}

function main(): void
{
    $direct = new DirectMagic();
    var_dump($direct->missing(1, second: 2));

    $inherited = new InheritedMagic();
    $values = [3, 4];
    var_dump($inherited->packed(...$values, tail: 5));

    // A declared base type is not an exact runtime type. The compiler must
    // retain Zend dispatch so a subclass's real method wins over __call().
    var_dump(callFromDeclaredBase(new RuntimeMethod()));
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(7) "missing"
  [1]=>
  array(2) {
    [0]=>
    int(1)
    ["second"]=>
    int(2)
  }
}
array(2) {
  [0]=>
  string(6) "packed"
  [1]=>
  array(3) {
    [0]=>
    int(3)
    [1]=>
    int(4)
    ["tail"]=>
    int(5)
  }
}
string(14) "runtime-method"
