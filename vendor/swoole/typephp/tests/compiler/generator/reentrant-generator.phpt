--TEST--
reentrant generator advance throws without corrupting outer generator state
--FILE--
<?php
class ReentrantGeneratorState
{
    public static mixed $generator = null;
}

function reentrant_generator(): iterable
{
    yield 1;
    try {
        ReentrantGeneratorState::$generator->next();
    } catch (Throwable $e) {
        echo get_class($e), ':', $e->getMessage(), "\n";
    }
    yield 2;
    return 3;
}

function main(): void
{
    $generator = reentrant_generator();
    ReentrantGeneratorState::$generator = $generator;
    var_dump($generator->current());
    $generator->next();
    var_dump($generator->current(), $generator->valid());
    $generator->next();
    var_dump($generator->valid(), $generator->getReturn());
}
?>
--EXPECT--
int(1)
Error:Cannot resume an already running generator
int(2)
bool(true)
bool(false)
int(3)
