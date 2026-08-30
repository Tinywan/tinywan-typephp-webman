--TEST--
Symfony Finder style lazy iterator: yield from callable property invocation
--SKIPIF--
<?php exit('skip Expr_YieldFrom is not supported yet'); ?>
--FILE--
<?php
class SymfonyLazyIteratorCase implements IteratorAggregate
{
    private Closure $iteratorFactory;

    public function __construct(callable $iteratorFactory)
    {
        $this->iteratorFactory = $iteratorFactory(...);
    }

    public function getIterator(): Traversable
    {
        yield from ($this->iteratorFactory)();
    }
}

function main(): void
{
    $iterator = new SymfonyLazyIteratorCase(static function (): Traversable {
        yield 'first' => 'alpha';
        yield 'second' => 'beta';
    });

    foreach ($iterator as $key => $value) {
        echo $key, '=', $value, "\n";
    }
}
?>
--EXPECT--
first=alpha
second=beta
