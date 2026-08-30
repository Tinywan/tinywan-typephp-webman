--TEST--
Owned call argument temporaries are released at the end of the call statement
--FILE--
<?php

final class TemporaryArgumentLifetimeProbe
{
    public static int $destroyed = 0;

    public function __destruct()
    {
        self::$destroyed++;
    }
}

function consume_temporary_object(TemporaryArgumentLifetimeProbe $value): void
{
}

function consume_temporary_array(array $values): void
{
}

function main(): void
{
    TemporaryArgumentLifetimeProbe::$destroyed = 0;
    consume_temporary_object(new TemporaryArgumentLifetimeProbe());
    echo 'object=', TemporaryArgumentLifetimeProbe::$destroyed, "\n";

    consume_temporary_array([new TemporaryArgumentLifetimeProbe()]);
    echo 'array=', TemporaryArgumentLifetimeProbe::$destroyed, "\n";
}
?>
--EXPECT--
object=1
array=2
