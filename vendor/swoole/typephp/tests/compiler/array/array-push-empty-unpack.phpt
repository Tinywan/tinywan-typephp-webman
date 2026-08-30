--TEST--
array_push retains its required array argument when an unpacked list is empty
--FILE--
<?php

function main(): void
{
    // Keep the required by-reference argument before an empty unpack.
    $values = [];
    array_push($values, ...[]);
    var_dump($values);

    array_push($values, ...[1, 2]);
    var_dump($values);
}
?>
--EXPECT--
array(0) {
}
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
