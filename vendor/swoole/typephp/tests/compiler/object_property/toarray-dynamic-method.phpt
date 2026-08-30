--TEST--
Dynamic toArray() supports scalar conversion, declared methods and object-property fallback
--FILE--
<?php

class DynamicToArrayValue
{
    public function toArray(): array
    {
        return ['value' => 42];
    }
}

class DynamicToArrayMagicOnly
{
    public int $value = 9;

    public function __call(string $name, array $arguments): array
    {
        return ['magic' => $name];
    }
}

function eraseToMixed(object $value): mixed
{
    return $value;
}

function callDynamicToArray(mixed $value): array
{
    return $value->toArray();
}

function callScalarToArray(int $value): array
{
    return $value->toArray();
}

function main(): void
{
    var_dump(callScalarToArray(42));
    var_dump(callDynamicToArray(null));
    var_dump(callDynamicToArray('value'));
    var_dump(callDynamicToArray(['key' => 7]));
    var_dump(callDynamicToArray(eraseToMixed(new DynamicToArrayValue())));
    var_dump((new DynamicToArrayMagicOnly())->toArray());
    var_dump(callDynamicToArray(eraseToMixed(new DynamicToArrayMagicOnly())));

    $plain = new stdClass();
    $plain->value = 7;
    var_dump(callDynamicToArray($plain));
    var_dump((array) $plain);
}
?>
--EXPECT--
array(1) {
  [0]=>
  int(42)
}
array(0) {
}
array(1) {
  [0]=>
  string(5) "value"
}
array(1) {
  ["key"]=>
  int(7)
}
array(1) {
  ["value"]=>
  int(42)
}
array(1) {
  ["value"]=>
  int(9)
}
array(1) {
  ["value"]=>
  int(9)
}
array(1) {
  ["value"]=>
  int(7)
}
array(1) {
  ["value"]=>
  int(7)
}
