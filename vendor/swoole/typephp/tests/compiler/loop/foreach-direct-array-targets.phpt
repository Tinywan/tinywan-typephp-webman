--TEST--
foreach direct array targets preserve keys, values, references, and assignment order
--FILE--
<?php

function collect(array $values): array
{
    $result = [];
    foreach ($values as $key => $value) {
        $result[] = $key . ':' . $value;
    }
    return $result;
}

function valuesOnly(array $values): array
{
    $result = [];
    foreach ($values as $value) {
        $result[] = $value;
    }
    return $result;
}

function sameTarget(array $values): mixed
{
    $item = null;
    foreach ($values as $item => $item) {
    }
    return $item;
}

function main(): void
{
    var_dump(collect([2 => 'two', 'name' => 'value']));
    var_dump(valuesOnly([10, 20]));
    var_dump(sameTarget([7 => 'last-value']));

    $source = std::any(10);
    $values = [&$source];
    foreach ($values as $value) {
        $value = 99;
    }
    var_dump($source, $value);

    $snapshot = ['a' => 1, 'b' => 2];
    $seen = [];
    foreach ($snapshot as $key => $value) {
        $seen[] = $key . ':' . $value;
        unset($snapshot[$key]);
    }
    var_dump($seen, $snapshot);
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(5) "2:two"
  [1]=>
  string(10) "name:value"
}
array(2) {
  [0]=>
  int(10)
  [1]=>
  int(20)
}
string(10) "last-value"
int(10)
int(99)
array(2) {
  [0]=>
  string(3) "a:1"
  [1]=>
  string(3) "b:2"
}
array(0) {
}
