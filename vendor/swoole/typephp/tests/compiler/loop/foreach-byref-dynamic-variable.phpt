--TEST--
Foreach by reference mutates the original dynamically typed variable without a COW temporary
--FILE--
<?php
function mutateUntyped($items): array {
    foreach ($items as &$item) {
        $item['value']++;
    }
    return $items;
}

function mutateMixed(mixed $items): array {
    foreach ($items as &$item) {
        $item['value'] += 2;
    }
    return $items;
}

function mutateNullable(?array $items): array {
    foreach ($items as &$item) {
        $item['value'] += 3;
    }
    return $items;
}

function mutateTyped(array $items): array {
    foreach ($items as &$item) {
        $item['value'] += 4;
    }
    return $items;
}

function main(): void {
    var_dump(mutateUntyped([['value' => 1], ['value' => 10]]));
    var_dump(mutateMixed([['value' => 1], ['value' => 10]]));
    var_dump(mutateNullable([['value' => 1], ['value' => 10]]));
    var_dump(mutateTyped([['value' => 1], ['value' => 10]]));
}
?>
--EXPECT--
array(2) {
  [0]=>
  array(1) {
    ["value"]=>
    int(2)
  }
  [1]=>
  array(1) {
    ["value"]=>
    int(11)
  }
}
array(2) {
  [0]=>
  array(1) {
    ["value"]=>
    int(3)
  }
  [1]=>
  array(1) {
    ["value"]=>
    int(12)
  }
}
array(2) {
  [0]=>
  array(1) {
    ["value"]=>
    int(4)
  }
  [1]=>
  array(1) {
    ["value"]=>
    int(13)
  }
}
array(2) {
  [0]=>
  array(1) {
    ["value"]=>
    int(5)
  }
  [1]=>
  array(1) {
    ["value"]=>
    int(14)
  }
}
