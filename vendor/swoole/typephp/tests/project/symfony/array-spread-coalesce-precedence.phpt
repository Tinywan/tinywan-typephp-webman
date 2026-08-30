--TEST--
Symfony pattern: array spread with null coalescing expression
--FILE--
<?php

function mergeVars(array $vars, array $options): array
{
    return [...$vars, ...$options['vars'] ?? []];
}

function main(): void
{
    var_dump(mergeVars(['a' => 1], ['vars' => ['b' => 2]]));
    var_dump(mergeVars(['a' => 1], []));
}
?>
--EXPECT--
array(2) {
  ["a"]=>
  int(1)
  ["b"]=>
  int(2)
}
array(1) {
  ["a"]=>
  int(1)
}
