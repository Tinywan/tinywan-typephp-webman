--TEST--
Arrow Functions - PHP 8.1+ short closure syntax
--FILE--
<?php
// Test basic arrow function
function test_basic_arrow() {
    $numbers = [1, 2, 3, 4, 5];
    $doubled = array_map(fn($n) => $n * 2, $numbers);
    return $doubled;
}

// Test arrow function with multiple parameters
function test_multi_param_arrow() {
    $pairs = [[1, 2], [3, 4], [5, 6]];
    $sums = array_map(fn($a, $b, $_unused) => $a + $b, ...$pairs);
    return $sums;
}

// Test arrow function capturing variables (by value)
function test_captured_variable($multiplier) {
    $numbers = [1, 2, 3];
    $multiplied = array_map(fn($n) => $n * $multiplier, $numbers);
    return $multiplied;
}

// Test nested arrow functions
function test_nested_arrow() {
    $numbers = [1, 2, 3, 4];
    $result = array_map(
        fn($n) => array_reduce([1, 2], fn($carry, $x) => $carry * $x, $n),
        $numbers
    );
    return $result;
}

// Test arrow function in filter
function test_filter_arrow() {
    $numbers = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
    $evens = array_filter($numbers, fn($n) => $n % 2 === 0);
    return array_values($evens);
}

// Test arrow function in reduce
function test_reduce_arrow() {
    $numbers = [1, 2, 3, 4, 5];
    $product = array_reduce($numbers, fn($carry, $n) => $carry * $n, 1);
    return $product;
}

// Test arrow function returning arrays
function test_array_return_arrow() {
    $items = [1, 2, 3];
    $transformed = array_map(fn($item) => [$item, $item * 2], $items);
    return $transformed;
}

// Test chained arrow function calls
class Calculator {
    private array $numbers;
    
    public function __construct(array $numbers) {
        $this->numbers = $numbers;
    }
    
    public function transform(callable $callback): self {
        $this->numbers = array_map($callback, $this->numbers);
        return $this;
    }
    
    public function filter(callable $callback): self {
        $this->numbers = array_values(array_filter($this->numbers, $callback));
        return $this;
    }
    
    public function getNumbers(): array {
        return $this->numbers;
    }
}

function main() {
    // Test basic arrow
    var_dump(test_basic_arrow());
    
    // Test multi-param arrow
    var_dump(test_multi_param_arrow());
    
    // Test captured variable
    var_dump(test_captured_variable(10));
    var_dump(test_captured_variable(100));
    
    // Test nested arrow
    var_dump(test_nested_arrow());
    
    // Test filter arrow
    var_dump(test_filter_arrow());
    
    // Test reduce arrow
    var_dump(test_reduce_arrow());
    
    // Test array return
    var_dump(test_array_return_arrow());
    
    // Test chained operations
    $calc = new Calculator([1, 2, 3, 4, 5]);
    $result = $calc
        ->transform(fn($n) => $n * 2)
        ->filter(fn($n) => $n > 5)
        ->transform(fn($n) => $n + 1)
        ->getNumbers();
    var_dump($result);
}
?>
--EXPECT--
array(5) {
  [0]=>
  int(2)
  [1]=>
  int(4)
  [2]=>
  int(6)
  [3]=>
  int(8)
  [4]=>
  int(10)
}
array(2) {
  [0]=>
  int(4)
  [1]=>
  int(6)
}
array(3) {
  [0]=>
  int(10)
  [1]=>
  int(20)
  [2]=>
  int(30)
}
array(3) {
  [0]=>
  int(100)
  [1]=>
  int(200)
  [2]=>
  int(300)
}
array(4) {
  [0]=>
  int(2)
  [1]=>
  int(4)
  [2]=>
  int(6)
  [3]=>
  int(8)
}
array(5) {
  [0]=>
  int(2)
  [1]=>
  int(4)
  [2]=>
  int(6)
  [3]=>
  int(8)
  [4]=>
  int(10)
}
int(120)
array(3) {
  [0]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    int(2)
  }
  [1]=>
  array(2) {
    [0]=>
    int(2)
    [1]=>
    int(4)
  }
  [2]=>
  array(2) {
    [0]=>
    int(3)
    [1]=>
    int(6)
  }
}
array(3) {
  [0]=>
  int(7)
  [1]=>
  int(9)
  [2]=>
  int(11)
}
