--TEST--
generator methods via Fiber iterator
--FILE--
<?php

class GeneratorMethodBox implements IteratorAggregate
{
    private array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function getIterator(): Traversable
    {
        foreach ($this->items as $key => $value) {
            yield $key => strtoupper($value);
        }
    }
}

function main(): void
{
    $box = new GeneratorMethodBox(['first' => 'alpha', 'second' => 'beta']);
    foreach ($box as $key => $value) {
        echo $key, '=', $value, "\n";
    }
}
?>
--EXPECT--
first=ALPHA
second=BETA
