--TEST--
Native variadic function calls accept named arguments
--FILE--
<?php
function collect(string $first = "root", ...$items): array
{
    return [$first, $items];
}

function collectInts(int ...$numbers): array
{
    return $numbers;
}

function main(): void
{
    var_dump(collect("A", 1, 2, tail: 3, items: 4));
    var_dump(collect(first: "B", extra: 5));
    var_dump(collectInts(one: 1, two: 2));
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(1) "A"
  [1]=>
  array(4) {
    [0]=>
    int(1)
    [1]=>
    int(2)
    ["tail"]=>
    int(3)
    ["items"]=>
    int(4)
  }
}
array(2) {
  [0]=>
  string(1) "B"
  [1]=>
  array(1) {
    ["extra"]=>
    int(5)
  }
}
array(2) {
  ["one"]=>
  int(1)
  ["two"]=>
  int(2)
}
