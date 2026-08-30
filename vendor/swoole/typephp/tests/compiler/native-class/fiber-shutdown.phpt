--TEST--
Native class: request shutdown safely detaches roots owned by suspended Fibers
--FILE--
<?php

#[Native]
class NativeFiberShutdownValue
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}

function suspendUntilRequestShutdown(): void
{
    $value = new NativeFiberShutdownValue('alive');
    Fiber::suspend();

    // This frame intentionally remains suspended until request shutdown.
    echo $value->name, "\n";
}

function main(): void
{
    global $suspendedFiber;

    $suspendedFiber = new Fiber(static function (): void {
        suspendUntilRequestShutdown();
    });
    $suspendedFiber->start();

    echo "suspended\n";
}

?>
--EXPECT--
suspended
