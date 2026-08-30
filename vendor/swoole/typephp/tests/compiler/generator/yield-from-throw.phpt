--TEST--
yield from delegates throw and preserves the child return value
--FILE--
<?php
function throwing_child(): iterable
{
    try {
        yield 'ready';
    } catch (RuntimeException $e) {
        yield 'child:' . $e->getMessage();
    }
    return 7;
}

function throwing_parent(): iterable
{
    $result = yield from throwing_child();
    yield 'return:' . $result;
}

function main(): void
{
    $generator = throwing_parent();
    var_dump($generator->current());
    var_dump($generator->throw(new RuntimeException('injected')));
    $generator->next();
    var_dump($generator->current());
}
?>
--EXPECT--
string(5) "ready"
string(14) "child:injected"
string(8) "return:7"
