--TEST--
generator lifecycle methods follow Zend Generator semantics
--FILE--
<?php
function lifecycle_generator(): iterable
{
    try {
        yield 'first' => 1;
        yield 'second' => 2;
    } catch (Exception $e) {
        yield 'caught' => $e->getMessage();
    }
    return 9;
}

function empty_generator(): iterable
{
    if (false) {
        yield 1;
    }
    return 7;
}

function main(): void
{
    $next = lifecycle_generator();
    $next->next();
    var_dump($next->key(), $next->current());
    try {
        $next->rewind();
    } catch (Throwable $e) {
        echo get_class($e), ': ', $e->getMessage(), "\n";
    }
    while ($next->valid()) {
        $next->next();
    }

    $throw = lifecycle_generator();
    var_dump($throw->throw(new Exception('injected')));
    var_dump($throw->key());

    while ($throw->valid()) {
        $throw->next();
    }
    $throw->next();
    var_dump($throw->send('ignored'));
    var_dump($throw->getReturn());

    $empty = empty_generator();
    var_dump($empty->send('ignored'));
    $empty->next();
    var_dump($empty->getReturn());

    try {
        $empty->throw(new RuntimeException('closed'));
    } catch (Throwable $e) {
        echo get_class($e), ': ', $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
string(6) "second"
int(2)
Exception: Cannot rewind a generator that was already run
string(8) "injected"
string(6) "caught"
NULL
int(9)
NULL
int(7)
RuntimeException: closed
