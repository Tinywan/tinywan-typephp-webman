--TEST--
Symfony pattern: anonymous IteratorAggregate with cached ??= ArrayObject
--FILE--
<?php

function wrapMiddleware(iterable $handlers): IteratorAggregate
{
    if ($handlers instanceof IteratorAggregate) {
        return $handlers;
    }

    if (is_array($handlers)) {
        return new ArrayObject($handlers);
    }

    return new class($handlers) implements IteratorAggregate {
        private ArrayObject $cachedIterator;

        public function __construct(
            private Traversable $middlewareHandlers,
        ) {
        }

        public function getIterator(): Traversable
        {
            return $this->cachedIterator ??= new ArrayObject(iterator_to_array($this->middlewareHandlers, false));
        }
    };
}

function main(): void
{
    $source = new ArrayIterator(['first', 'second']);
    $aggregate = wrapMiddleware($source);

    foreach ($aggregate as $value) {
        var_dump($value);
    }

    foreach ($aggregate as $value) {
        var_dump('cached-'.$value);
    }
}
?>
--EXPECT--
string(5) "first"
string(6) "second"
string(12) "cached-first"
string(13) "cached-second"
