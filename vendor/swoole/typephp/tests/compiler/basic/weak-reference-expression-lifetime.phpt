--TEST--
Expression temporaries release owned objects at the end of the PHP statement
--FILE--
<?php

final class TemporaryLifetimeProbe
{
    public static int $destroyed = 0;

    public function __destruct()
    {
        self::$destroyed++;
    }
}

function make_temporary_probe(): object
{
    return new TemporaryLifetimeProbe();
}

function main(): void
{
    $target = new stdClass();
    $weak = WeakReference::create($target);
    $liveIdentity = $weak->get() === $target;
    unset($target);
    gc_collect_cycles();

    var_dump($liveIdentity);
    var_dump($weak->get() === null);

    $different = make_temporary_probe() === new stdClass();
    var_dump($different);
    var_dump(TemporaryLifetimeProbe::$destroyed);
}

?>
--EXPECT--
bool(true)
bool(true)
bool(false)
int(1)
