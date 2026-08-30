--TEST--
uncaught generator exceptions cross the Fiber boundary safely
--FILE--
<?php
function failing_generator(): iterable
{
    yield 1;
    throw new RuntimeException('generator failed');
}

function main(): void
{
    $generator = failing_generator();
    var_dump($generator->current());
    try {
        $generator->next();
    } catch (Throwable $e) {
        echo get_class($e), ': ', $e->getMessage(), "\n";
    }
    var_dump($generator->valid());
    try {
        $generator->getReturn();
    } catch (Throwable $e) {
        echo get_class($e), ': ', $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
int(1)
RuntimeException: generator failed
bool(false)
Exception: Cannot get return value of a generator that hasn't returned
