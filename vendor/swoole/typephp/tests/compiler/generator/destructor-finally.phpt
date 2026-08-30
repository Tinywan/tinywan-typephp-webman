--TEST--
suspended generator destruction closes its Fiber without leaking
--FILE--
<?php
function generator_with_finally(): iterable
{
    try {
        yield 1;
        yield 2;
    } finally {
        echo "finally\n";
    }
}

function main(): void
{
    $generator = generator_with_finally();
    var_dump($generator->current());
    unset($generator);
    gc_collect_cycles();
}
?>
--EXPECT--
int(1)
finally
