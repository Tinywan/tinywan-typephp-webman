--TEST--
array_values reindexes array_unique result
--FILE--
<?php

function unique_defs(array $defs): array
{
    return array_values(array_unique($defs));
}

function main(): void
{
    var_dump(unique_defs(['a', 'b', 'a', 'c', 'b']));
    var_dump(unique_defs([2 => 'x', 5 => 'x', 9 => 'y']));
}
?>
--EXPECT--
array(3) {
  [0]=>
  string(1) "a"
  [1]=>
  string(1) "b"
  [2]=>
  string(1) "c"
}
array(2) {
  [0]=>
  string(1) "x"
  [1]=>
  string(1) "y"
}
