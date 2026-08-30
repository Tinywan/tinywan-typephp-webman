--TEST--
closed generator releases captured values while preserving its return value
--FILE--
<?php
class GeneratorRetentionState
{
    public static ?WeakReference $weak = null;
}

function retaining_generator(object $captured): iterable
{
    yield 1;
    return 2;
}

function make_retaining_generator(): iterable
{
    $captured = new stdClass();
    GeneratorRetentionState::$weak = WeakReference::create($captured);
    return retaining_generator($captured);
}

function main(): void
{
    $generator = make_retaining_generator();
    $generator->current();
    $generator->next();
    gc_collect_cycles();
    var_dump($generator->getReturn());
    var_dump(GeneratorRetentionState::$weak?->get() === null);
}
?>
--EXPECT--
int(2)
bool(true)
