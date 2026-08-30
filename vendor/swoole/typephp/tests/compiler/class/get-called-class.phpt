--TEST--
static::class uses the runtime called scope
--FILE--
<?php
declare(strict_types=1);

class CalledClassParent
{
    public static function name(): string
    {
        return static::class;
    }
}

class CalledClassChild extends CalledClassParent
{
}

function main(): void
{
    var_dump(CalledClassParent::name());
    var_dump(CalledClassChild::name());
}
?>
--EXPECT--
string(17) "CalledClassParent"
string(16) "CalledClassChild"
