--TEST--
Iterable and Iterator - Custom iteration with Traversable
--FILE--
<?php
// Test implementing Iterator interface
class NumberIterator implements Iterator {
    private int $start;
    private int $end;
    private int $current = 0;
    
    public function __construct(int $start, int $end) {
        $this->start = $start;
        $this->end = $end;
        $this->current = $start;
    }
    
    public function rewind(): void {
        $this->current = $this->start;
    }
    
    public function current(): int {
        return $this->current;
    }
    
    public function key(): int {
        return $this->current - $this->start;
    }
    
    public function next(): void {
        $this->current++;
    }
    
    public function valid(): bool {
        return $this->current <= $this->end;
    }
}

// Test implementing IteratorAggregate interface
class NumberCollection implements IteratorAggregate {
    private array $numbers;
    
    public function __construct(array $numbers) {
        $this->numbers = $numbers;
    }
    
    public function getIterator(): Traversable {
        return new ArrayIterator($this->numbers);
    }
}

// Test a simple iterable range
class Range implements IteratorAggregate {
    private int $start;
    private int $end;
    
    public function __construct(int $start, int $end) {
        $this->start = $start;
        $this->end = $end;
    }
    
    public function getIterator(): Traversable {
        for ($i = $this->start; $i <= $this->end; $i++) {
            yield $i;
        }
    }
}

// Test filtering iterator
class NumberFilterIterator implements Iterator {
    private Iterator $iterator;
    private mixed $filterValue;
    
    public function __construct(Iterator $iterator, mixed $filterValue) {
        $this->iterator = $iterator;
        $this->filterValue = $filterValue;
        
        // Rewind to first valid element
        $this->iterator->rewind();
        $this->advanceToValid();
    }
    
    private function advanceToValid(): void {
        while ($this->iterator->valid() && $this->iterator->current() < $this->filterValue) {
            $this->iterator->next();
        }
    }
    
    public function rewind(): void {
        $this->iterator->rewind();
        $this->advanceToValid();
    }
    
    public function current(): mixed {
        return $this->iterator->current();
    }
    
    public function key(): mixed {
        return $this->iterator->key();
    }
    
    public function next(): void {
        $this->iterator->next();
        $this->advanceToValid();
    }
    
    public function valid(): bool {
        return $this->iterator->valid();
    }
}

function main() {
    // Test custom Iterator
    echo "Custom Iterator:\n";
    $iterator = new NumberIterator(5, 10);
    foreach ($iterator as $key => $value) {
        echo "{$key}: {$value}\n";
    }
    
    // Test IteratorAggregate with ArrayIterator
    echo "\nIteratorAggregate:\n";
    $collection = new NumberCollection([10, 20, 30, 40, 50]);
    foreach ($collection as $index => $number) {
        echo "{$index}: {$number}\n";
    }
    
    // Test Generator-based IteratorAggregate
    echo "\nGenerator-based:\n";
    $range = new Range(1, 5);
    foreach ($range as $num) {
        echo "{$num}\n";
    }
    
    // Test FilterIterator
    echo "\nFilterIterator:\n";
    $baseIterator = new NumberIterator(1, 10);
    $filterIterator = new NumberFilterIterator($baseIterator, 5);
    foreach ($filterIterator as $value) {
        echo "{$value}\n";
    }
    
    // Test multiple iterations
    echo "\nMultiple iterations:\n";
    $reusable = new Range(1, 3);
    foreach ($reusable as $num) {
        echo "First: {$num}\n";
    }
    foreach ($reusable as $num) {
        echo "Second: {$num}\n";
    }
}
?>
--EXPECT--
Custom Iterator:
0: 5
1: 6
2: 7
3: 8
4: 9
5: 10

IteratorAggregate:
0: 10
1: 20
2: 30
3: 40
4: 50

Generator-based:
1
2
3
4
5

FilterIterator:
5
6
7
8
9
10

Multiple iterations:
First: 1
First: 2
First: 3
Second: 1
Second: 2
Second: 3
