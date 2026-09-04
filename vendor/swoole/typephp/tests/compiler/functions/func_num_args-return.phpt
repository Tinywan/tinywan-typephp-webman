--TEST--
func_num_args static count can be returned without leaking an ambiguous C++ integer literal
--FILE--
<?php

function staticArgumentCountUntyped($value = null)
{
    return func_num_args();
}

function staticArgumentCountTyped($first = null, $second = 5): int
{
    return func_num_args();
}

function staticVariadicArgumentCount($first = null, ...$rest)
{
    return func_num_args();
}

class StaticArgumentCount
{
    public function method($value = null)
    {
        return func_num_args();
    }

    public static function staticMethod($value = null): int
    {
        return func_num_args();
    }
}

function main(): void
{
    // TypePHP intentionally reports the statically materialized parameter
    // slots, including omitted optional parameters filled by their defaults.
    var_dump(staticArgumentCountUntyped());
    var_dump(staticArgumentCountUntyped(10));
    var_dump(staticArgumentCountTyped());
    var_dump(staticArgumentCountTyped(10));

    var_dump(staticVariadicArgumentCount());
    var_dump(staticVariadicArgumentCount(10, 20, 30));

    $object = new StaticArgumentCount();
    var_dump($object->method());
    var_dump(StaticArgumentCount::staticMethod());
}
?>
--EXPECT--
int(1)
int(1)
int(2)
int(2)
int(1)
int(3)
int(1)
int(1)
