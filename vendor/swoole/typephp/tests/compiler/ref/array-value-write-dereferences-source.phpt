--TEST--
ordinary array writes copy referenced sources while explicit references stay linked
--FILE--
<?php

function main()
{
    $source = 1;
    $reference =& $source;

    $appended = [];
    $appended[] = $reference;

    $keyed = [];
    $keyed['value'] = $reference;

    $literal = [$reference];
    $mixedLiteral = ['value' => $reference, $reference];
    $explicitReference = [&$reference];

    $reference = 2;
    var_dump($appended, $keyed, $literal, $mixedLiteral, $explicitReference);

    $target = 10;
    $targetArray = [&$target];
    $other = 20;
    $otherReference =& $other;
    $targetArray[0] = $otherReference;
    $otherReference = 30;
    var_dump($target, $targetArray, $other);

    $nestedSource = [&$otherReference];
    $nestedCopy = [];
    $nestedCopy[] = $nestedSource[0];
    $otherReference = 40;
    var_dump($nestedCopy, $nestedSource);
}
?>
--EXPECT--
array(1) {
  [0]=>
  int(1)
}
array(1) {
  ["value"]=>
  int(1)
}
array(1) {
  [0]=>
  int(1)
}
array(2) {
  ["value"]=>
  int(1)
  [0]=>
  int(1)
}
array(1) {
  [0]=>
  &int(2)
}
int(20)
array(1) {
  [0]=>
  &int(20)
}
int(30)
array(1) {
  [0]=>
  int(30)
}
array(1) {
  [0]=>
  &int(40)
}
