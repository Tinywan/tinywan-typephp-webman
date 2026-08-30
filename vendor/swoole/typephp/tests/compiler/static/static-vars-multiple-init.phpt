--TEST--
multiple static variables initialize independently once
--FILE--
<?php

function init_static(string $name, int $value): int
{
    echo "init:$name:$value\n";
    return $value;
}

function next_pair(int $seed): void
{
    static $a = init_static('a', 10), $b = init_static('b', 20);

    $a += $seed;
    $b += $seed * 2;

    var_dump([$a, $b]);
}

function main(): void
{
    next_pair(1);
    next_pair(2);
}
?>
--EXPECT--
init:a:10
init:b:20
array(2) {
  [0]=>
  int(11)
  [1]=>
  int(22)
}
array(2) {
  [0]=>
  int(13)
  [1]=>
  int(26)
}
