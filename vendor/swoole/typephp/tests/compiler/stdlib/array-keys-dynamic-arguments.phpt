--TEST--
array_keys optimized calls preserve dynamic arguments, strict types, and evaluation order
--FILE--
<?php
declare(strict_types=1);

final class ArrayKeysOptions
{
    public bool $strict = true;
}

function arrayKeysDynamicStrict(array &$events): bool
{
    $events[] = 'strict';
    return true;
}

function arrayKeysDynamicValues(array &$events): array
{
    $events[] = 'array';
    return ['integer' => 1, 'string' => '1'];
}

function arrayKeysDynamicFilter(array &$events): mixed
{
    $events[] = 'filter';
    return '1';
}

function arrayKeysMixedBool(): mixed
{
    return true;
}

function arrayKeysMixedInt(): mixed
{
    return 1;
}

function arrayKeysMixedArray(): mixed
{
    return [];
}

function arrayKeysUnionInt(): bool|int
{
    return 1;
}

function main()
{
    $values = ['integer' => 1, 'string' => '1'];

    var_dump(array_keys($values));
    var_dump(array_keys($values, '1'));
    var_dump(array_keys($values, '1', true));

    $strict = true;
    var_dump(array_keys($values, '1', $strict));

    $options = new ArrayKeysOptions();
    var_dump(array_keys($values, '1', $options->strict));

    $events = [];
    var_dump(array_keys(
        arrayKeysDynamicValues($events),
        arrayKeysDynamicFilter($events),
        arrayKeysDynamicStrict($events)
    ));
    var_dump($events);

    var_dump(array_keys($values, '1', arrayKeysMixedBool()));

    try {
        array_keys($values, '1', arrayKeysMixedInt());
        echo "mixed-int=missing TypeError\n";
    } catch (TypeError $error) {
        echo "mixed-int=TypeError\n";
    }

    try {
        array_keys($values, '1', arrayKeysMixedArray());
        echo "mixed-array=missing TypeError\n";
    } catch (TypeError $error) {
        echo "mixed-array=TypeError\n";
    }

    try {
        array_keys($values, '1', arrayKeysUnionInt());
        echo "union-int=missing TypeError\n";
    } catch (TypeError $error) {
        echo "union-int=TypeError\n";
    }
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(7) "integer"
  [1]=>
  string(6) "string"
}
array(2) {
  [0]=>
  string(7) "integer"
  [1]=>
  string(6) "string"
}
array(1) {
  [0]=>
  string(6) "string"
}
array(1) {
  [0]=>
  string(6) "string"
}
array(1) {
  [0]=>
  string(6) "string"
}
array(1) {
  [0]=>
  string(6) "string"
}
array(3) {
  [0]=>
  string(5) "array"
  [1]=>
  string(6) "filter"
  [2]=>
  string(6) "strict"
}
array(1) {
  [0]=>
  string(6) "string"
}
mixed-int=TypeError
mixed-array=TypeError
union-int=TypeError
