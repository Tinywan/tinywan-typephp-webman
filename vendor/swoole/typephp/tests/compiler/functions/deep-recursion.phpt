--TEST--
Deep Recursion Test
--FILE--
<?php

// Test deep recursion with factorial
function factorial(int $n): int {
    if ($n <= 1) {
        return 1;
    }
    return $n * factorial($n - 1);
}

// Test fibonacci with memoization
function fib(int $n, array &$memo): int {
    if ($n <= 1) {
        return 1;
    }
    
    if (isset($memo[$n])) {
        return $memo[$n];
    }
    
    $result = fib($n - 1, $memo) + fib($n - 2, $memo);
    $memo[$n] = $result;
    return $result;
}

// Test mutual recursion
function isEven(int $n): bool {
    if ($n === 0) {
        return true;
    }
    return isOdd($n - 1);
}

function isOdd(int $n): bool {
    if ($n === 0) {
        return false;
    }
    return isEven($n - 1);
}

// Test tree recursion
function sumTree(array $tree): int {
    $sum = $tree['value'];
    
    foreach ($tree['children'] ?? [] as $child) {
        $sum += sumTree($child);
    }
    
    return $sum;
}

// Test quicksort (divide and conquer)
function quicksort(array $arr): array {
    if (count($arr) <= 1) {
        return $arr;
    }
    
    $pivot = $arr[0];
    $left = [];
    $right = [];
    
    for ($i = 1; $i < count($arr); $i++) {
        if ($arr[$i] < $pivot) {
            $left[] = $arr[$i];
        } else {
            $right[] = $arr[$i];
        }
    }
    
    return array_merge(quicksort($left), [$pivot], quicksort($right));
}

function main() {
    var_dump(factorial(5));
    var_dump(factorial(10));

    $memo = [];
    var_dump(fib(10, $memo));
    var_dump(fib(15, $memo));

    var_dump(isEven(10));
    var_dump(isOdd(10));
    var_dump(isEven(7));
    var_dump(isOdd(7));

    $tree = [
        'value' => 1,
        'children' => [
            [
                'value' => 2,
                'children' => [
                    ['value' => 4, 'children' => []],
                    ['value' => 5, 'children' => []],
                ],
            ],
            [
                'value' => 3,
                'children' => [
                    ['value' => 6, 'children' => []],
                ],
            ],
        ],
    ];

    var_dump(sumTree($tree));

    $unsorted = [3, 6, 1, 5, 2, 4];
    var_dump(quicksort($unsorted));
}
?>
--EXPECT--
int(120)
int(3628800)
int(89)
int(987)
bool(true)
bool(false)
bool(false)
bool(true)
int(21)
array(6) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
  [3]=>
  int(4)
  [4]=>
  int(5)
  [5]=>
  int(6)
}
