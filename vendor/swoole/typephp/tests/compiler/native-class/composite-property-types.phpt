--TEST--
Native class: nullable, union and intersection fields use Var with runtime type checks
--FILE--
<?php

interface NativeCompositeLeft {}
interface NativeCompositeRight {}

class NativeCompositeBoth implements NativeCompositeLeft, NativeCompositeRight {}
class NativeCompositeLeftOnly implements NativeCompositeLeft {}

#[Native]
class NativeCompositeProperties
{
    public ?int $nullableInt;
    public int|string|null $unionValue;
    public NativeCompositeLeft&NativeCompositeRight $intersectionValue;
}

function writeNullable(NativeCompositeProperties $object, mixed $value): void
{
    $object->nullableInt = $value;
}

function writeUnion(NativeCompositeProperties $object, mixed $value): void
{
    $object->unionValue = $value;
}

function writeIntersection(NativeCompositeProperties $object, mixed $value): void
{
    $object->intersectionValue = $value;
}

function main(): void
{
    $object = new NativeCompositeProperties();
    var_dump($object->nullableInt, $object->unionValue, $object->intersectionValue);

    writeNullable($object, 42);
    writeUnion($object, 'ok');
    writeIntersection($object, new NativeCompositeBoth());
    var_dump($object->nullableInt, $object->unionValue, $object->intersectionValue::class);

    try {
        writeNullable($object, 'bad');
    } catch (TypeError $error) {
        echo "type error\n";
    }
    try {
        writeUnion($object, []);
    } catch (TypeError $error) {
        echo "type error\n";
    }
    try {
        writeIntersection($object, new NativeCompositeLeftOnly());
    } catch (TypeError $error) {
        echo "type error\n";
    }
}
?>
--EXPECT--
NULL
NULL
NULL
int(42)
string(2) "ok"
string(19) "NativeCompositeBoth"
type error
type error
type error
