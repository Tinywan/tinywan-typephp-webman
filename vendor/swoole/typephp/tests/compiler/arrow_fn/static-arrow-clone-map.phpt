--TEST--
static arrow function with typed parameter return clone in array_map
--FILE--
<?php

class CloneMapItem
{
    public int $value;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function __clone()
    {
        $this->value++;
    }
}

function clone_items(array $items): array
{
    return array_map(static fn (CloneMapItem $item): CloneMapItem => clone $item, $items);
}

function main(): void
{
    $items = [new CloneMapItem(1), new CloneMapItem(10)];
    $cloned = clone_items($items);

    var_dump($items[0]->value, $items[1]->value);
    var_dump($cloned[0]->value, $cloned[1]->value);
    var_dump($items[0] === $cloned[0]);
}
?>
--EXPECT--
int(1)
int(10)
int(2)
int(11)
bool(false)
