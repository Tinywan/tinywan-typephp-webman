--TEST--
By-reference variadic unpack preserves writeback, COW separation, keys and existing references
--FILE--
<?php

function increment_all(&...$values): array
{
    foreach ($values as &$value) {
        $value++;
    }
    unset($value);
    return array_keys($values);
}

function main(): void
{
    $source = [1, 2];
    $copy = $source;
    var_dump(increment_all(...$source));
    var_dump($source, $copy);

    $named = ['left' => 10, 'right' => 20];
    var_dump(increment_all(...$named));
    var_dump($named);

    $first = [30];
    $second = [40, 50];
    var_dump(increment_all(...$first, ...$second));
    var_dump($first, $second);

    $external = 60;
    $references = [&$external];
    increment_all(...$references);
    var_dump($external, $references);

    // A temporary has no caller-visible slots, but remains a valid unpack.
    var_dump(increment_all(...[70, 80]));
}
?>
--EXPECT--
array(2) {
  [0]=>
  int(0)
  [1]=>
  int(1)
}
array(2) {
  [0]=>
  int(2)
  [1]=>
  int(3)
}
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
array(2) {
  [0]=>
  string(4) "left"
  [1]=>
  string(5) "right"
}
array(2) {
  ["left"]=>
  int(11)
  ["right"]=>
  int(21)
}
array(3) {
  [0]=>
  int(0)
  [1]=>
  int(1)
  [2]=>
  int(2)
}
array(1) {
  [0]=>
  int(31)
}
array(2) {
  [0]=>
  int(41)
  [1]=>
  int(51)
}
int(61)
array(1) {
  [0]=>
  &int(61)
}
array(2) {
  [0]=>
  int(0)
  [1]=>
  int(1)
}
