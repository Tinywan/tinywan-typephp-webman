--TEST--
generator union signatures validate parameters without checking the yielded return value
--FILE--
<?php
function union_generator(int|string $value): Iterator|array
{
    yield $value;
    return 42;
}

function main(): void
{
    $generator = union_generator('valid');
    var_dump($generator->current());
    $generator->next();
    var_dump($generator->getReturn());

    try {
        union_generator(any([]));
    } catch (Throwable $e) {
        echo get_class($e), "\n";
    }
}
?>
--EXPECT--
string(5) "valid"
int(42)
TypeError
