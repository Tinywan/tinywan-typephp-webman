--TEST--
Symfony Console pattern: closure caches iterable values with coalesce assignment
--FILE--
<?php

function valueCallback(iterable $values): Closure
{
    $valueCache = null;

    return static function () use (&$valueCache, $values): array {
        return $valueCache ??= iterator_to_array($values, false);
    };
}

function main(): void
{
    $callback = valueCallback(new ArrayIterator(['a' => 'A', 'b' => 'B']));

    var_dump($callback());
    var_dump($callback());
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(1) "A"
  [1]=>
  string(1) "B"
}
array(2) {
  [0]=>
  string(1) "A"
  [1]=>
  string(1) "B"
}
