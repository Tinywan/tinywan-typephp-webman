--TEST--
yield from calls Iterator current before key
--FILE--
<?php
class YieldFromOrderIterator implements Iterator
{
    private int $index = 0;

    public function rewind(): void { echo "rewind\n"; }
    public function valid(): bool { echo "valid\n"; return $this->index < 1; }
    public function current(): mixed { echo "current\n"; return 1; }
    public function key(): mixed { echo "key\n"; return 0; }
    public function next(): void { echo "next\n"; ++$this->index; }
}

function ordered_yield_from(): iterable
{
    yield from new YieldFromOrderIterator();
}

function main(): void
{
    foreach (ordered_yield_from() as $key => $value) {
        echo "body\n";
    }
}
?>
--EXPECT--
rewind
valid
current
key
body
next
valid
