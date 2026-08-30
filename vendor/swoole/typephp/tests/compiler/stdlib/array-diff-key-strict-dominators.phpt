--TEST--
array_keys after array_diff_key with integer block ids
--FILE--
<?php

function strict_dominators(array $dominators, int $block): array
{
    return array_keys(array_diff_key($dominators[$block], [$block => true]));
}

function main(): void
{
    $dominators = [
        0 => [0 => true],
        1 => [0 => true, 1 => true],
        2 => [0 => true, 1 => true, 2 => true],
    ];

    var_dump(strict_dominators($dominators, 0));
    var_dump(strict_dominators($dominators, 2));
}
?>
--EXPECT--
array(0) {
}
array(2) {
  [0]=>
  int(0)
  [1]=>
  int(1)
}
