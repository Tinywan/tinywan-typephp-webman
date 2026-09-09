--TEST--
single-offset isset and coalesce on static arrays preserve PHP key and null semantics
--FILE--
<?php

class StaticPresence
{
    public static array $values = [
        'present' => 42,
        'null' => null,
        2 => 'two',
        '' => 'empty-key',
    ];

    public static function has(mixed $key): bool
    {
        return isset(self::$values[$key]);
    }

    public static function get(mixed $key): mixed
    {
        return self::$values[$key] ?? 'fallback';
    }
}

function main(): void
{
    var_dump(StaticPresence::has('present'));
    var_dump(StaticPresence::has('null'));
    var_dump(StaticPresence::has('missing'));
    var_dump(StaticPresence::has(2.9));
    var_dump(StaticPresence::has(null));
    var_dump(StaticPresence::get('present'));
    var_dump(StaticPresence::get('null'));
    var_dump(StaticPresence::get(null));
}
?>
--EXPECT--
bool(true)
bool(false)
bool(false)
bool(true)
bool(true)
int(42)
string(8) "fallback"
string(9) "empty-key"
