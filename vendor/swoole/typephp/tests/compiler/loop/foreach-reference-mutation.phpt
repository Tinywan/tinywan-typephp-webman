--TEST--
foreach reference preserves COW, live mutations, and loop-variable aliases
--FILE--
<?php

function main(): void
{
    $values = [1, 2];
    $copy = $values;
    foreach ($values as &$value) {
        $value *= 10;
    }
    unset($value);
    var_dump($values, $copy);

    $values = [1, 2];
    $seen = [];
    foreach ($values as &$value) {
        $seen[] = $value;
        if ($value === 1) {
            $values[] = 3;
        }
    }
    unset($value);
    var_dump($seen);

    $values = [1, 2, 3];
    $seen = [];
    foreach ($values as $key => &$value) {
        $seen[] = [$key, $value];
        if ($key === 0) {
            unset($values[1]);
        }
    }
    unset($value);
    var_dump($seen);

    $value = 99;
    foreach ($values as &$value) {
        ++$value;
    }
    unset($value);
    foreach ([7, 8] as $value) {
        echo $value, "\n";
    }

    $linked = [1, 2];
    foreach ($linked as &$slot) {
    }
    foreach ([4, 5] as $slot) {
    }
    unset($slot);
    var_dump($linked);
}
?>
--EXPECT--
array(2) {
  [0]=>
  int(10)
  [1]=>
  int(20)
}
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
array(2) {
  [0]=>
  array(2) {
    [0]=>
    int(0)
    [1]=>
    int(1)
  }
  [1]=>
  array(2) {
    [0]=>
    int(2)
    [1]=>
    int(3)
  }
}
7
8
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(5)
}
