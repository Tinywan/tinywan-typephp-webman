--TEST--
TypedProperty lists allow sparse and negative integer keys after unset
--FILE--
<?php

class TypedPropertyListWithHoles
{
    #[StdList(Type::String)]
    public array $values = [];
}

#[Native]
class NativeTypedPropertyListWithHoles
{
    #[StdList(Type::Int)]
    public array $values = [];
}

function writeListValue(TypedPropertyListWithHoles $box, int $index, string $value): void
{
    $box->values[$index] = $value;
}

function writeNativeListValue(NativeTypedPropertyListWithHoles $box, int $index, int $value): void
{
    $box->values[$index] = $value;
}

function main(): void
{
    $box = new TypedPropertyListWithHoles();
    writeListValue($box, 0, 'zero');
    $box->values[] = 'one';
    $box->values[] = 'two';

    unset($box->values[1]);
    writeListValue($box, 3, 'three');

    unset($box->values[3]);
    writeListValue($box, 4, 'four');
    writeListValue($box, 1, 'one-again');

    var_dump($box->values);

    writeListValue($box, 6, 'gap');
    writeListValue($box, -2, 'negative');
    var_dump($box->values[6], $box->values[-2]);

    $native = new NativeTypedPropertyListWithHoles();
    $native->values[] = 10;
    $native->values[] = 20;
    unset($native->values[1]);
    writeNativeListValue($native, 2, 30);
    unset($native->values[2]);
    writeNativeListValue($native, 3, 40);
    var_dump($native->values);
}
?>
--EXPECT--
array(4) {
  [0]=>
  string(4) "zero"
  [2]=>
  string(3) "two"
  [4]=>
  string(4) "four"
  [1]=>
  string(9) "one-again"
}
string(3) "gap"
string(8) "negative"
array(2) {
  [0]=>
  int(10)
  [3]=>
  int(40)
}
