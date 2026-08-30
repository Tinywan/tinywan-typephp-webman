--TEST--
An exception handler may include a PHP file during shutdown
--FILE--
<?php

function handleShutdownException(Throwable $exception): void
{
    global $shutdownState;

    echo 'state:', $shutdownState, "\n";
    echo 'handled:', $exception->getMessage(), "\n";

    $file = tempnam(sys_get_temp_dir(), 'typephp-shutdown-');
    file_put_contents($file, '<?php echo "included during shutdown\\n";');
    include $file;
    unlink($file);
}

function throwDuringShutdown(): void
{
    throw new RuntimeException('shutdown failure');
}

function main(): void
{
    global $shutdownState;

    $shutdownState = 'request alive';
    set_exception_handler('handleShutdownException');
    register_shutdown_function('throwDuringShutdown');
    echo "main completed\n";
}
?>
--EXPECT--
main completed
state:request alive
handled:shutdown failure
included during shutdown
