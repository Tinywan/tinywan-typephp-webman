--TEST--
ArrayAccess ??= preserves offsetExists, offsetGet, offsetSet and lazy evaluation semantics
--FILE--
<?php
declare(strict_types=1);

final class CoalesceBag implements ArrayAccess
{
    public array $data = [];

    public function offsetExists(mixed $offset): bool
    {
        echo "exists:$offset\n";
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet(mixed $offset): mixed
    {
        echo "get:$offset\n";
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        echo "set:$offset=$value\n";
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }
}

final class CoalesceHolder
{
    public function __construct(public CoalesceBag $bag)
    {
    }
}

function coalesceRhs(string $label, int $value): int
{
    echo "rhs:$label\n";
    return $value;
}

function throwingCoalesceRhs(): int
{
    echo "rhs:throw\n";
    throw new RuntimeException('failed');
}

function coalesceThroughInterface(ArrayAccess $target, string $key, int $value): mixed
{
    return $target[$key] ??= coalesceRhs('interface', $value);
}

function coalesceThroughMixed(mixed $target, string $key, int $value): array
{
    $result = $target[$key] ??= coalesceRhs('mixed', $value);
    return [$result, $target];
}

function coalesceThroughNullableObject(?ArrayAccess $target): mixed
{
    return $target['nullable'] ??= coalesceRhs('nullable-object', 34);
}

function coalesceReceiver(CoalesceBag $target, int &$calls): CoalesceBag
{
    $calls++;
    echo "receiver:$calls\n";
    return $target;
}

function coalesceKey(int &$calls): string
{
    $calls++;
    echo "key:$calls\n";
    return 'side';
}

function main(): void
{
    echo "-- missing --\n";
    $missing = new CoalesceBag();
    var_dump($missing['service'] ??= coalesceRhs('missing', 42));
    var_dump($missing->data);

    echo "-- present --\n";
    $present = new CoalesceBag();
    $present->data['service'] = 7;
    var_dump($present['service'] ??= coalesceRhs('present', 99));
    var_dump($present->data);

    echo "-- null --\n";
    $null = new CoalesceBag();
    $null->data['service'] = null;
    var_dump($null['service'] ??= coalesceRhs('null', 21));
    var_dump($null->data);

    echo "-- interface --\n";
    $interface = new CoalesceBag();
    var_dump(coalesceThroughInterface($interface, 'typed', 13));
    var_dump($interface->data);

    echo "-- object property container --\n";
    $holder = new CoalesceHolder(new CoalesceBag());
    var_dump($holder->bag['property'] ??= coalesceRhs('property', 14));
    var_dump($holder->bag->data);

    echo "-- mixed object --\n";
    $mixedObject = new CoalesceBag();
    [$mixedObjectResult] = coalesceThroughMixed($mixedObject, 'dynamic', 31);
    var_dump($mixedObjectResult, $mixedObject->data);

    echo "-- mixed object present --\n";
    $mixedPresent = new CoalesceBag();
    $mixedPresent->data['dynamic'] = 8;
    [$mixedPresentResult] = coalesceThroughMixed($mixedPresent, 'dynamic', 99);
    var_dump($mixedPresentResult, $mixedPresent->data);

    echo "-- mixed object null --\n";
    $mixedNull = new CoalesceBag();
    $mixedNull->data['dynamic'] = null;
    [$mixedNullResult] = coalesceThroughMixed($mixedNull, 'dynamic', 33);
    var_dump($mixedNullResult, $mixedNull->data);

    echo "-- mixed array --\n";
    [$mixedArrayResult, $mixedArray] = coalesceThroughMixed([], 'dynamic', 32);
    var_dump($mixedArrayResult, $mixedArray);

    echo "-- nullable object currently null --\n";
    var_dump(coalesceThroughNullableObject(null));

    echo "-- receiver and key once --\n";
    $sideEffect = new CoalesceBag();
    $receiverCalls = 0;
    $keyCalls = 0;
    var_dump(coalesceReceiver($sideEffect, $receiverCalls)[coalesceKey($keyCalls)]
        ??= coalesceRhs('side', 55));
    var_dump($receiverCalls, $keyCalls, $sideEffect->data);

    echo "-- unused result --\n";
    $unused = new CoalesceBag();
    $unused['value'] ??= coalesceRhs('unused', 66);
    var_dump($unused->data);

    echo "-- throwing rhs --\n";
    $throwing = new CoalesceBag();
    try {
        $throwing['value'] ??= throwingCoalesceRhs();
    } catch (RuntimeException $e) {
        echo "caught\n";
    }
    var_dump($throwing->data);

    echo "-- ArrayObject --\n";
    $arrayObject = new ArrayObject();
    var_dump($arrayObject['value'] ??= coalesceRhs('array-object', 77));
    var_dump($arrayObject->getArrayCopy());
}
?>
--EXPECT--
-- missing --
exists:service
rhs:missing
set:service=42
int(42)
array(1) {
  ["service"]=>
  int(42)
}
-- present --
exists:service
get:service
int(7)
array(1) {
  ["service"]=>
  int(7)
}
-- null --
exists:service
get:service
rhs:null
set:service=21
int(21)
array(1) {
  ["service"]=>
  int(21)
}
-- interface --
exists:typed
rhs:interface
set:typed=13
int(13)
array(1) {
  ["typed"]=>
  int(13)
}
-- object property container --
exists:property
rhs:property
set:property=14
int(14)
array(1) {
  ["property"]=>
  int(14)
}
-- mixed object --
exists:dynamic
rhs:mixed
set:dynamic=31
int(31)
array(1) {
  ["dynamic"]=>
  int(31)
}
-- mixed object present --
exists:dynamic
get:dynamic
int(8)
array(1) {
  ["dynamic"]=>
  int(8)
}
-- mixed object null --
exists:dynamic
get:dynamic
rhs:mixed
set:dynamic=33
int(33)
array(1) {
  ["dynamic"]=>
  int(33)
}
-- mixed array --
rhs:mixed
int(32)
array(1) {
  ["dynamic"]=>
  int(32)
}
-- nullable object currently null --
rhs:nullable-object
int(34)
-- receiver and key once --
receiver:1
key:1
exists:side
rhs:side
set:side=55
int(55)
int(1)
int(1)
array(1) {
  ["side"]=>
  int(55)
}
-- unused result --
exists:value
rhs:unused
set:value=66
array(1) {
  ["value"]=>
  int(66)
}
-- throwing rhs --
exists:value
rhs:throw
caught
array(0) {
}
-- ArrayObject --
rhs:array-object
int(77)
array(1) {
  ["value"]=>
  int(77)
}
