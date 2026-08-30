--TEST--
array_push with unpacked values mutates first argument once
--FILE--
<?php

function make_push_values(): array
{
    echo "make-values\n";
    return [2, 3];
}

function main(): void
{
    $items = [1];
    $count = array_push($items, ...make_push_values());

    var_dump($count);
    var_dump($items);
}
?>
--EXPECT--
make-values
int(3)
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
