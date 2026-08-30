--TEST--
Native class: Iterator and IteratorAggregate lower foreach to direct method calls
--FILE--
<?php

#[Native]
class NativeRangeIterator implements Iterator
{
    public int $position = 0;
    public int $limit = 0;

    public function __construct(int $limit)
    {
        $this->limit = $limit;
    }

    public function rewind(): void
    {
        echo 'R';
        $this->position = 0;
    }

    public function valid(): bool
    {
        echo 'V';
        return $this->position < $this->limit;
    }

    public function current(): int
    {
        echo 'C';
        return ($this->position + 1) * 10;
    }

    public function key(): string
    {
        echo 'K';
        return 'k' . $this->position;
    }

    public function next(): void
    {
        echo 'N';
        $this->position++;
    }
}

#[Native]
class NativeRangeAggregate implements IteratorAggregate
{
    public int $limit = 0;

    public function __construct(int $limit)
    {
        $this->limit = $limit;
    }

    public function getIterator(): NativeRangeIterator
    {
        echo 'A';
        return new NativeRangeIterator($this->limit);
    }
}

#[Native]
class NativeChildIterator extends NativeRangeIterator
{
    public function current(): int
    {
        echo 'D';
        return 77;
    }
}

function makePolymorphicIterator(): NativeRangeIterator
{
    return new NativeChildIterator(1);
}

#[Native]
class NativePhpAggregate implements IteratorAggregate
{
    public function getIterator(): ArrayIterator
    {
        echo 'P';
        return new ArrayIterator(['x' => 7, 'y' => 8]);
    }
}

#[Native]
class NativeIteratedValue
{
    public int $number = 0;

    public function __construct(int $number)
    {
        $this->number = $number;
    }
}

#[Native]
class NativeObjectIterator implements Iterator
{
    public bool $available = true;

    public function rewind(): void
    {
        $this->available = true;
    }

    public function valid(): bool
    {
        return $this->available;
    }

    public function current(): NativeIteratedValue
    {
        return new NativeIteratedValue(99);
    }

    public function key(): int
    {
        return 5;
    }

    public function next(): void
    {
        $this->available = false;
    }
}

function main(): void
{
    $iterator = new NativeRangeIterator(3);
    foreach ($iterator as $key => $value) {
        echo "[$key=$value]";
        if ($value === 10) {
            continue;
        }
        if ($value === 20) {
            break;
        }
    }
    echo PHP_EOL;

    foreach (new NativeRangeIterator(1) as $value) {
        echo "[$value]";
    }
    echo PHP_EOL;

    $captured = new NativeRangeIterator(2);
    foreach ($captured as $capturedValue) {
        echo "[$capturedValue]";
        $captured = null;
    }
    echo PHP_EOL;

    foreach (makePolymorphicIterator() as $polymorphicValue) {
        echo "[$polymorphicValue]";
    }
    echo PHP_EOL;

    $aggregate = new NativeRangeAggregate(2);
    foreach ($aggregate as $key => $value) {
        echo "[$key=$value]";
    }
    echo PHP_EOL;

    $phpAggregate = new NativePhpAggregate();
    foreach ($phpAggregate as $phpKey => $phpValue) {
        echo "[$phpKey=$phpValue]";
    }
    echo PHP_EOL;

    $objects = new NativeObjectIterator();
    foreach ($objects as $objectKey => $objectValue) {
        echo "[$objectKey={$objectValue->number}]";
    }
    echo PHP_EOL;
}
?>
--EXPECT--
RVCK[k0=10]NVCK[k1=20]
RVC[10]NV
RVC[10]NVC[20]NV
RVD[77]NV
ARVCK[k0=10]NVCK[k1=20]NV
P[x=7][y=8]
[5=99]
