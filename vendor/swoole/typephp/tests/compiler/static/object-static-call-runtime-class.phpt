--TEST--
Object static calls use the runtime class rather than the declared type
--FILE--
<?php

class ObjectStaticCallBase
{
    public static function identify(): string
    {
        return 'base';
    }

    public static function calledClass(): string
    {
        return static::class;
    }

    public static function increment(int &$value): string
    {
        $value++;
        return static::class;
    }
}

final class ObjectStaticCallChild extends ObjectStaticCallBase
{
    public static function identify(): string
    {
        return 'child';
    }
}

function callOnTypedObject(ObjectStaticCallBase $object): array
{
    return [$object::identify(), $object::calledClass()];
}

function callOnMixedObject(mixed $object): array
{
    return [$object::identify(), $object::calledClass()];
}

function callOnGenericObject(object $object): array
{
    return [$object::identify(), $object::calledClass()];
}

function callOnExactLocalObject(): array
{
    $object = new ObjectStaticCallChild();
    return [$object::identify(), $object::calledClass()];
}

function callViaGetClass(ObjectStaticCallBase $object): array
{
    return [get_class($object)::identify(), get_class($object)::calledClass()];
}

function callOnClassString(string $class): array
{
    return [$class::identify(), $class::calledClass()];
}

function callReferenceArgument(ObjectStaticCallBase $object): array
{
    $value = 1;
    $class = $object::increment($value);
    return [$class, $value];
}

function main(): void
{
    $object = new ObjectStaticCallChild();
    var_dump(callOnTypedObject($object));
    var_dump(callOnMixedObject($object));
    var_dump(callOnGenericObject($object));
    var_dump(callOnExactLocalObject());
    var_dump(callViaGetClass($object));
    var_dump(callOnClassString(ObjectStaticCallChild::class));
    var_dump(callReferenceArgument($object));
}

?>
--EXPECT--
array(2) {
  [0]=>
  string(5) "child"
  [1]=>
  string(21) "ObjectStaticCallChild"
}
array(2) {
  [0]=>
  string(5) "child"
  [1]=>
  string(21) "ObjectStaticCallChild"
}
array(2) {
  [0]=>
  string(5) "child"
  [1]=>
  string(21) "ObjectStaticCallChild"
}
array(2) {
  [0]=>
  string(5) "child"
  [1]=>
  string(21) "ObjectStaticCallChild"
}
array(2) {
  [0]=>
  string(5) "child"
  [1]=>
  string(21) "ObjectStaticCallChild"
}
array(2) {
  [0]=>
  string(5) "child"
  [1]=>
  string(21) "ObjectStaticCallChild"
}
array(2) {
  [0]=>
  string(21) "ObjectStaticCallChild"
  [1]=>
  int(2)
}
