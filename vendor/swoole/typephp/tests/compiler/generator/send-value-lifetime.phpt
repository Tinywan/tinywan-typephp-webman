--TEST--
generator send preserves refcounted values across a Fiber suspension
--FILE--
<?php
function receive_values(): iterable
{
    $string = yield 'string';
    var_dump($string);
    $array = yield 'array';
    var_dump($array);
    $object = yield 'object';
    var_dump($object->value);
}

function main(): void
{
    $generator = receive_values();
    var_dump($generator->current());
    var_dump($generator->send(str_repeat('x', 32)));
    var_dump($generator->send(['key' => str_repeat('y', 16)]));
    $object = new stdClass();
    $object->value = 42;
    var_dump($generator->send($object));
}
?>
--EXPECT--
string(6) "string"
string(32) "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
string(5) "array"
array(1) {
  ["key"]=>
  string(16) "yyyyyyyyyyyyyyyy"
}
string(6) "object"
int(42)
NULL
