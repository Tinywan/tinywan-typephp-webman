--TEST--
Symfony pattern: dynamic first-class callable branch
--FILE--
<?php

final class CallableTarget
{
    public function format(string $value): string
    {
        return 'object:'.strtoupper($value);
    }
}

function format_global(string $value): string
{
    return 'function:'.strtolower($value);
}

function makeCallable(array $callable): Closure
{
    return $callable[0] ? $callable[0]->{$callable[1]}(...) : $callable[1](...);
}

function main(): void
{
    $objectCallable = makeCallable([new CallableTarget(), 'format']);
    $functionCallable = makeCallable([null, 'format_global']);

    var_dump($objectCallable('symfony'));
    var_dump($functionCallable('AOT'));
}
?>
--EXPECT--
string(14) "object:SYMFONY"
string(12) "function:aot"
