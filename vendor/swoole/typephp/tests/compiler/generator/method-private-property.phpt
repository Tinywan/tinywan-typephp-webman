--TEST--
generator methods can access private properties
--FILE--
<?php
class PrivateGeneratorBox implements IteratorAggregate
{
    private array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function getIterator(): Traversable
    {
        foreach ($this->items as $item) {
            yield $item;
        }
    }
}

function main(): void
{
    foreach (new PrivateGeneratorBox([1]) as $value) {
        echo $value, "\n";
    }
}
?>
--EXPECTF--
1
