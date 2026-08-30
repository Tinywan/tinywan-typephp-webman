--TEST--
Repeated static calls to a runtime-defined magic class use transient callables safely
--FILE--
<?php
function main(): void
{
    eval(<<<'PHP'
class RuntimeMagicStaticCall
{
    public static function __callStatic(string $name, array $arguments): string
    {
        return $name . ':' . $arguments[0];
    }
}
PHP);

    for ($i = 0; $i < 3; $i++) {
        var_dump(RuntimeMagicStaticCall::missing($i));
    }
}
?>
--EXPECT--
string(9) "missing:0"
string(9) "missing:1"
string(9) "missing:2"
