--TEST--
static variable dynamic initializer runs only once
--FILE--
<?php

function make_static_items(int $seed): array
{
    echo "init:$seed\n";
    return [$seed];
}

function use_static_items(int $seed): void
{
    static $items = make_static_items($seed);
    var_dump($items);
    $items[] = $seed;
}

function main(): void
{
    use_static_items(10);
    use_static_items(20);
}
?>
--EXPECT--
init:10
array(1) {
  [0]=>
  int(10)
}
array(2) {
  [0]=>
  int(10)
  [1]=>
  int(10)
}
