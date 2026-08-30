--TEST--
throw on a new generator that returns before yielding rethrows the supplied exception
--FILE--
<?php
function empty_before_throw(): iterable
{
    if (false) {
        yield 1;
    }
    return 7;
}

function main(): void
{
    $generator = empty_before_throw();
    try {
        $generator->throw(new RuntimeException('new-empty'));
    } catch (Throwable $e) {
        echo get_class($e), ':', $e->getMessage(), "\n";
    }
    var_dump($generator->getReturn());
}
?>
--EXPECT--
RuntimeException:new-empty
int(7)
