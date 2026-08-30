--TEST--
PHP 8.5 clone is available as a first-class callable and string callable
--SKIPIF--
<?php
if (PHP_VERSION_ID < 80500) {
    die('skip requires PHP 8.5');
}
?>
--FILE--
<?php

class CloneCallableValue
{
    public function __construct(public int $value) {}
}

function main(): void
{
    $source = new CloneCallableValue(7);
    $callable = clone(...);

    $first = $callable($source, ['value' => 8]);
    $mapped = array_map('clone', [$source, $first]);

    var_dump($source !== $first, $first->value);
    var_dump($mapped[0] !== $source, $mapped[0]->value);
    var_dump($mapped[1] !== $first, $mapped[1]->value);
}
?>
--EXPECT--
bool(true)
int(8)
bool(true)
int(7)
bool(true)
int(8)
