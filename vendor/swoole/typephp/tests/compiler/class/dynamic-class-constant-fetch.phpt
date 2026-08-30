--TEST--
dynamic class constant fetch and object ::class
--FILE--
<?php

class DynamicClassConstFirst
{
    public const NAME = 'first';
}

class DynamicClassConstSecond
{
    public const NAME = 'second';
}

function pick_class(bool $child): string
{
    echo 'pick:', $child ? 'child' : 'base', "\n";
    return $child ? DynamicClassConstSecond::class : DynamicClassConstFirst::class;
}

function make_dynamic_object(): object
{
    echo "object\n";
    return new DynamicClassConstSecond();
}

function main(): void
{
    $class = DynamicClassConstFirst::class;
    var_dump($class::NAME);

    var_dump(pick_class(true)::NAME);
    var_dump(pick_class(false)::NAME);

    $object = new DynamicClassConstSecond();
    var_dump($object::NAME);

    var_dump(make_dynamic_object()::class);
}
?>
--EXPECT--
string(5) "first"
pick:child
string(6) "second"
pick:base
string(5) "first"
string(6) "second"
object
string(23) "DynamicClassConstSecond"
