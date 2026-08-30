--TEST--
Tuple multi-return production safely consumes final local uses
--FILE--
<?php
function multi_return_owned_values(): array
{
    $text = 'owned';
    $array = [1, 2];
    $object = new stdClass();
    $object->value = 3;
    return [$text, $array, $object];
}

function multi_return_repeated_value(): array
{
    $value = ['repeated'];
    $tail = 'tail';
    return [$value, $value, $tail];
}

function multi_return_reference_value(&$value): array
{
    $alias =& $value;
    return [$alias, $alias];
}

function multi_return_global_value(): array
{
    global $multiReturnGlobal;
    return [$multiReturnGlobal, $multiReturnGlobal];
}

function multi_return_static_value(): array
{
    static $value = ['static'];
    return [$value, $value];
}

function main(): void
{
    global $multiReturnGlobal;
    $multiReturnGlobal = ['global'];

    [$text, $array, $object] = multi_return_owned_values();
    var_dump($text, $array, $object->value);

    [$first, $second, $tail] = multi_return_repeated_value();
    $first[] = 'changed';
    var_dump($first, $second, $tail);

    $source = 'reference';
    [$referenceFirst, $referenceSecond] = multi_return_reference_value($source);
    $referenceFirst = 'changed';
    var_dump($source, $referenceFirst, $referenceSecond);

    $compatibleArray = multi_return_reference_value($source);
    $compatibleArray[0] = 'array changed';
    var_dump($source, $compatibleArray);

    [$globalFirst, $globalSecond] = multi_return_global_value();
    $globalFirst[] = 'changed';
    var_dump($multiReturnGlobal, $globalFirst, $globalSecond);

    [$staticFirst, $staticSecond] = multi_return_static_value();
    $staticFirst[] = 'changed';
    var_dump($staticFirst, $staticSecond, multi_return_static_value());
}
?>
--EXPECT--
string(5) "owned"
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
int(3)
array(2) {
  [0]=>
  string(8) "repeated"
  [1]=>
  string(7) "changed"
}
array(1) {
  [0]=>
  string(8) "repeated"
}
string(4) "tail"
string(9) "reference"
string(7) "changed"
string(9) "reference"
string(9) "reference"
array(2) {
  [0]=>
  string(13) "array changed"
  [1]=>
  string(9) "reference"
}
array(1) {
  [0]=>
  string(6) "global"
}
array(2) {
  [0]=>
  string(6) "global"
  [1]=>
  string(7) "changed"
}
array(1) {
  [0]=>
  string(6) "global"
}
array(2) {
  [0]=>
  string(6) "static"
  [1]=>
  string(7) "changed"
}
array(1) {
  [0]=>
  string(6) "static"
}
array(2) {
  [0]=>
  array(1) {
    [0]=>
    string(6) "static"
  }
  [1]=>
  array(1) {
    [0]=>
    string(6) "static"
  }
}
