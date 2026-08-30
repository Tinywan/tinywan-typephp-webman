--TEST--
First-Class Callable Syntax (PHP 8.1+)
--FILE--
<?php

// Test basic first-class callable
function add(int $a, int $b): int {
    return $a + $b;
}

// Test first-class callable with class methods
class Math {
    public static function multiply(int $a, int $b): int {
        return $a * $b;
    }
    
    public function divide(int $a, int $b): float {
        return floatval($a) / $b;
    }
}

// Test callable returning callable
function multiplier(int $factor): callable {
    return fn(int $value) => $value * $factor;
}

function main() {
    $callable = 'add';
    var_dump(array_map($callable, [1, 2, 3], [4, 5, 6]));

    $staticCallable = ['Math', 'multiply'];
    var_dump(array_reduce([[2, 3], [4, 5]], fn($carry, $item) => $carry + call_user_func_array($staticCallable, $item), 0));

    $math = new Math();
    $instanceCallable = [$math, 'divide'];
    var_dump(call_user_func($instanceCallable, 10, 4));

    // Test first-class callable with arrow functions
    $filter = fn(array $arr): array => array_filter($arr, fn($n) => $n > 0);
    var_dump($filter([1, -2, 3, -4, 5]));

    // Test callable in array operations
    $numbers = range(1, 5);
    $doubled = array_map(fn($n) => $n * 2, $numbers);
    var_dump($doubled);

    $squared = array_map(fn($n) => $n ** 2, $numbers);
    var_dump($squared);

    // Test callable with usort
    $unsorted = [5, 2, 8, 1, 9];
    usort($unsorted, fn($a, $b) => $a <=> $b);
    var_dump($unsorted);
}
?>
--EXPECT--
array(3) {
  [0]=>
  int(5)
  [1]=>
  int(7)
  [2]=>
  int(9)
}
int(26)
float(2.5)
array(3) {
  [0]=>
  int(1)
  [2]=>
  int(3)
  [4]=>
  int(5)
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
array(5) {
  [0]=>
  int(1)
  [1]=>
  int(4)
  [2]=>
  int(9)
  [3]=>
  int(16)
  [4]=>
  int(25)
}
array(5) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(5)
  [3]=>
  int(8)
  [4]=>
  int(9)
}
