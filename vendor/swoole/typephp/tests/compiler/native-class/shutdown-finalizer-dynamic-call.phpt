--TEST--
Native class: shutdown finalizers can use request-local dynamic call caches
--FILE--
<?php

function shutdown_dynamic_target(string $value): string
{
    return 'finalizer:' . $value;
}

#[Native]
class ShutdownDynamicCaller
{
    public function __destruct()
    {
        $callback = 'shutdown_dynamic_target';
        echo $callback('ok') . "\n";
    }
}

function main(): void
{
    global $shutdownObject;
    $shutdownObject = new ShutdownDynamicCaller();
    echo "main\n";
}
?>
--EXPECT--
main
finalizer:ok
