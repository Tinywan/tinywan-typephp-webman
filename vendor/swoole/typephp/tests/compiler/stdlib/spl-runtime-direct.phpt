--TEST--
SPL iterator, object identity, and constant functions use direct wrappers
--FILE--
<?php

function makeIterator(): Iterator
{
    return new ArrayIterator(['name' => 'phpx', 4 => 42]);
}

function main(): void
{
    var_dump(iterator_count(makeIterator()));
    var_dump(iterator_to_array(makeIterator()));
    var_dump(iterator_to_array(makeIterator(), false));

    $object = new stdClass();
    var_dump(spl_object_id($object) > 0);
    var_dump(strlen(spl_object_hash($object)) === 32);
    var_dump(spl_object_id($object) === spl_object_id($object));

    define('TYPEPHP_RUNTIME_FAST_CONSTANT', ['ok' => true]);
    var_dump(constant('TYPEPHP_RUNTIME_FAST_CONSTANT'));

    try {
        iterator_count(42);
    } catch (TypeError $error) {
        echo $error->getMessage(), "\n";
    }

    try {
        spl_object_hash('invalid');
    } catch (TypeError $error) {
        echo $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
int(2)
array(2) {
  ["name"]=>
  string(4) "phpx"
  [4]=>
  int(42)
}
array(2) {
  [0]=>
  string(4) "phpx"
  [1]=>
  int(42)
}
bool(true)
bool(true)
bool(true)
array(1) {
  ["ok"]=>
  bool(true)
}
iterator_count(): Argument #1 ($iterator) must be of type Traversable|array, int given
spl_object_hash(): Argument #1 ($object) must be of type object, string given
