--TEST--
Native class: root frames remain valid across non-LIFO Fiber suspension
--FILE--
<?php

#[Native]
class NativeFiberValue
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}

function suspendedNativeFrame(): void
{
    $value = new NativeFiberValue('suspended');
    Fiber::suspend();
    echo $value->name, "\n";
}

function resumeFromNewerNativeFrame(Fiber $fiber): void
{
    $value = new NativeFiberValue('newer');
    $fiber->resume();
    echo $value->name, "\n";
}

function main(): void
{
    $fiber = new Fiber(static function (): void {
        suspendedNativeFrame();
    });
    $fiber->start();
    resumeFromNewerNativeFrame($fiber);
}

?>
--EXPECT--
suspended
newer
