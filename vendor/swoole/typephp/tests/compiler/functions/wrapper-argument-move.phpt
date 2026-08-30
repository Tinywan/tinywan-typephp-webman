--TEST--
Zend wrappers safely consume dead by-value arguments
--FILE--
<?php
function wrapper_argument_move(
    mixed $value,
    string $text,
    array $items,
    object $object,
    int $count,
    &$reference,
    ...$rest,
): array {
    $reference = 'updated';
    $result = [$value, $text, $items[0], $object->name, $count, $rest];
    return $result;
}

function main(): void
{
    $reference = 'before';
    $object = (object) ['name' => 'object'];
    $result = wrapper_argument_move('mixed', 'text', [10], $object, 5, $reference, 'x', 'y');
    var_dump($reference, $result);
}
?>
--EXPECT--
string(7) "updated"
array(6) {
  [0]=>
  string(5) "mixed"
  [1]=>
  string(4) "text"
  [2]=>
  int(10)
  [3]=>
  string(6) "object"
  [4]=>
  int(5)
  [5]=>
  array(2) {
    [0]=>
    string(1) "x"
    [1]=>
    string(1) "y"
  }
}
