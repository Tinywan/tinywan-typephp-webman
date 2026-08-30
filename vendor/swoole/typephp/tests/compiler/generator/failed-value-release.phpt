--TEST--
failed generator releases captured values while retaining failed state
--FILE--
<?php
class FailedGeneratorRetentionState
{
    public static ?WeakReference $weak = null;
}

function failing_retaining_generator(object $captured): iterable
{
    if (false) {
        yield 1;
    }
    throw new RuntimeException('failed');
}

function make_failing_retaining_generator(): iterable
{
    $captured = new stdClass();
    FailedGeneratorRetentionState::$weak = WeakReference::create($captured);
    return failing_retaining_generator($captured);
}

function main(): void
{
    $generator = make_failing_retaining_generator();
    try {
        $generator->current();
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }
    gc_collect_cycles();
    var_dump($generator->valid());
    var_dump(FailedGeneratorRetentionState::$weak?->get() === null);
}
?>
--EXPECT--
failed
bool(false)
bool(true)
