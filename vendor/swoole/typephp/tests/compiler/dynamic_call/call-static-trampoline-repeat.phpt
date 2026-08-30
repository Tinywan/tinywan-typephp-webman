--TEST--
Repeated dynamic static calls do not reuse a released Zend trampoline
--FILE--
<?php
class RepeatedMagicStaticCall
{
    public static function __callStatic(string $name, array $arguments): string
    {
        return $name . ':' . $arguments[0];
    }
}

function main(): void
{
    for ($i = 0; $i < 3; $i++) {
        var_dump(RepeatedMagicStaticCall::missing($i));
    }
}
?>
--EXPECT--
string(9) "missing:0"
string(9) "missing:1"
string(9) "missing:2"
