--TEST--
PHP 8.5 clone-with supports dynamic, numeric and object-valued properties
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80500) {
    die('skip requires PHP 8.5');
}
?>
--FILE--
<?php

function main(): void
{
    $source = new stdClass();
    $source->original = 'source';

    $copy = clone($source, [
        0 => 'zero',
        'named' => 'value',
        'source' => $source,
    ]);
    $properties = get_object_vars($copy);

    var_dump($source !== $copy);
    var_dump($source->original, $copy->original);
    var_dump($properties[0], $properties['named']);
    var_dump($copy->source === $source);
}
?>
--EXPECT--
bool(true)
string(6) "source"
string(6) "source"
string(4) "zero"
string(5) "value"
bool(true)
