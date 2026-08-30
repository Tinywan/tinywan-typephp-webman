--TEST--
Symfony pattern: array_map with first-class builtin callable
--FILE--
<?php

function normalizeExpectedTypes(array $expectedTypes): array
{
    return $expectedTypes ? array_map(strval(...), $expectedTypes) : $expectedTypes;
}

function main(): void
{
    var_dump(normalizeExpectedTypes([1, 2.5, true, 'name']));
    var_dump(normalizeExpectedTypes([]));
}
?>
--EXPECT--
array(4) {
  [0]=>
  string(1) "1"
  [1]=>
  string(3) "2.5"
  [2]=>
  string(1) "1"
  [3]=>
  string(4) "name"
}
array(0) {
}
