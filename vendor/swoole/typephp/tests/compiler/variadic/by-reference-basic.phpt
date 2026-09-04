--TEST--
By-reference variadic parameters preserve direct, named and method arguments
--FILE--
<?php

function suffix(string $suffix, &...$values): array
{
    foreach ($values as &$value) {
        $value .= $suffix;
    }
    unset($value);
    return array_keys($values);
}

class VariadicReferenceMutator
{
    public static function increment(&...$values): void
    {
        foreach ($values as &$value) {
            $value++;
        }
        unset($value);
    }

    public function double(&...$values): void
    {
        foreach ($values as &$value) {
            $value *= 2;
        }
        unset($value);
    }
}

class VariadicReferenceTarget
{
    public int $value = 10;
    public static int $staticValue = 20;
}

function main(): void
{
    var_dump(suffix('!'));

    $first = 'first';
    $second = 'second';
    var_dump(suffix('!', $first, $second));
    var_dump($first, $second);

    $left = 'left';
    $right = 'right';
    var_dump(suffix(suffix: '?', left: $left, right: $right));
    var_dump($left, $right);

    $one = 1;
    $two = 2;
    VariadicReferenceMutator::increment($one, $two);
    var_dump($one, $two);

    $mutator = new VariadicReferenceMutator();
    $mutator->double($one, $two);
    var_dump($one, $two);

    $array = [7];
    $target = new VariadicReferenceTarget();
    VariadicReferenceMutator::increment(
        $array[0],
        $target->value,
        VariadicReferenceTarget::$staticValue,
    );
    var_dump($array, $target->value, VariadicReferenceTarget::$staticValue);

    // As in PHP, passing an undefined variable by reference creates it.
    VariadicReferenceMutator::increment($createdByReference);
    var_dump($createdByReference);

    $parameter = (new ReflectionFunction('suffix'))->getParameters()[1];
    var_dump($parameter->isVariadic(), $parameter->isPassedByReference());
}
?>
--EXPECT--
array(0) {
}
array(2) {
  [0]=>
  int(0)
  [1]=>
  int(1)
}
string(6) "first!"
string(7) "second!"
array(2) {
  [0]=>
  string(4) "left"
  [1]=>
  string(5) "right"
}
string(5) "left?"
string(6) "right?"
int(2)
int(3)
int(4)
int(6)
array(1) {
  [0]=>
  int(8)
}
int(11)
int(21)
int(1)
bool(true)
bool(true)
