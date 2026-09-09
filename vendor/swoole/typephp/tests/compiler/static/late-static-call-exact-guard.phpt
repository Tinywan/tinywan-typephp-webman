--TEST--
late static calls use the exact-class fast path without bypassing subclass dispatch
--FILE--
<?php

class StaticCallBase
{
    public static function data(): array
    {
        return [static::class, 'base'];
    }

    public static function label(): string
    {
        $data = static::data();
        return $data[0] . ':' . $data[1];
    }
}

class StaticCallInherited extends StaticCallBase
{
}

class StaticCallOverride extends StaticCallBase
{
    public static function data(): array
    {
        return [static::class, 'override'];
    }
}

function main(): void
{
    var_dump(StaticCallBase::label());
    var_dump(StaticCallInherited::label());
    var_dump(StaticCallOverride::label());
}
?>
--EXPECT--
string(19) "StaticCallBase:base"
string(24) "StaticCallInherited:base"
string(27) "StaticCallOverride:override"
