--TEST--
unset evaluates array dimension expressions left to right
--FILE--
<?php

function unset_key(string $key): string
{
    echo "unset-key:$key\n";
    return $key;
}

function main(): void
{
    $items = ['a' => 1, 'b' => 2, 'c' => 3];

    unset($items[unset_key('a')], $items[unset_key('c')]);

    var_dump($items);
}
?>
--EXPECT--
unset-key:a
unset-key:c
array(1) {
  ["b"]=>
  int(2)
}
