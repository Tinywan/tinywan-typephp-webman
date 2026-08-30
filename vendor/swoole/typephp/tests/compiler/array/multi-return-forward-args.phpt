--TEST--
Multi-return Array adapter safely consumes its by-value parameters
--FILE--
<?php
function multi_return_forward_args(
    mixed $value,
    string $text,
    array $items,
    object $object,
    int $count,
    &$reference,
    ...$rest,
): array {
    $reference = 'updated';
    return [$value, $text, $items, $object, $count, $reference, $rest];
}

function multi_return_forward_defaults(
    string $text = 'default',
    array $items = [],
    ...$rest,
): array {
    return [$text, $items, $rest];
}

function main(): void
{
    $object = (object) ['name' => 'direct'];
    $directReference = 'before';
    [$value, $text, $items, $directObject, $count, $returnedReference, $rest]
        = multi_return_forward_args('value', 'text', [1], $object, 2, $directReference, 'x');
    var_dump(
        $value,
        $text,
        $items,
        $directObject->name,
        $count,
        $directReference,
        $returnedReference,
        $rest,
    );

    $arrayReference = 'before';
    $array = multi_return_forward_args('array', 'adapter', [3], $object, 4, $arrayReference, 'y', 'z');
    var_dump($arrayReference, $array[0], $array[1], $array[2], $array[3]->name, $array[4], $array[5], $array[6]);

    $defaults = multi_return_forward_defaults();
    [$defaultText, $defaultItems, $defaultRest] = multi_return_forward_defaults(rest: 'named');
    var_dump($defaults, $defaultText, $defaultItems, $defaultRest);
}
?>
--EXPECT--
string(5) "value"
string(4) "text"
array(1) {
  [0]=>
  int(1)
}
string(6) "direct"
int(2)
string(7) "updated"
string(7) "updated"
array(1) {
  [0]=>
  string(1) "x"
}
string(7) "updated"
string(5) "array"
string(7) "adapter"
array(1) {
  [0]=>
  int(3)
}
string(6) "direct"
int(4)
string(7) "updated"
array(2) {
  [0]=>
  string(1) "y"
  [1]=>
  string(1) "z"
}
array(3) {
  [0]=>
  string(7) "default"
  [1]=>
  array(0) {
  }
  [2]=>
  array(0) {
  }
}
string(7) "default"
array(0) {
}
array(1) {
  ["rest"]=>
  string(5) "named"
}
