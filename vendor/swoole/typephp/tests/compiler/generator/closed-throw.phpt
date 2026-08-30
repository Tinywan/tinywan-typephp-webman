--TEST--
throw on a closed generator rethrows without leaking the exception
--FILE--
<?php
function closed_generator(): iterable
{
    if (false) {
        yield 1;
    }
}

function main(): void
{
    $generator = closed_generator();
    $generator->valid();
    try {
        $generator->throw(new RuntimeException('closed'));
    } catch (Throwable $e) {
        echo get_class($e), ': ', $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
RuntimeException: closed
