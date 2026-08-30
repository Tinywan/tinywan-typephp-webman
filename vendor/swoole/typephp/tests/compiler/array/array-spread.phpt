--TEST--
Spread Operator in Arrays - Array unpacking with ...
--FILE--
<?php
// Test basic array spreading
function test_basic_spread() {
    $part1 = [1, 2, 3];
    $part2 = [4, 5, 6];
    return [...$part1, ...$part2];
}

// Test spread with additional elements
function test_spread_with_elements() {
    $middle = [2, 3, 4];
    return [1, ...$middle, 5];
}

// Test multiple spreads
function test_multiple_spreads() {
    $a = [1, 2];
    $b = [3, 4];
    $c = [5, 6];
    return [...$a, ...$b, ...$c];
}

// Test spread with keys
function test_spread_with_keys() {
    $arr1 = ['a' => 1, 'b' => 2];
    $arr2 = ['c' => 3, 'd' => 4];
    return [...$arr1, ...$arr2];
}

// Test spread in middle of array
function create_user_record($id, $name, $extra = []) {
    return [
        'id' => $id,
        'name' => $name,
        ...$extra,
        'active' => true,
    ];
}

// Test nested spreading
function test_nested_spread() {
    $inner = [7, 8];
    $outer = [1, 2, [...$inner], 9, 10];
    return $outer;
}

// Test spread with string keys (overwriting)
function test_string_key_spread() {
    $defaults = ['status' => 'active', 'role' => 'user'];
    $override = ['role' => 'admin'];
    return [...$defaults, ...$override];
}

// Test spread empty arrays
function test_spread_empty() {
    $empty = [];
    $data = [1, 2, 3];
    return [...$empty, ...$data, ...$empty];
}

function main() {
    // Test basic spread
    var_dump(test_basic_spread());
    
    // Test spread with elements
    var_dump(test_spread_with_elements());
    
    // Test multiple spreads
    var_dump(test_multiple_spreads());
    
    // Test spread with keys
    var_dump(test_spread_with_keys());
    
    // Test spread in function return
    var_dump(create_user_record(1, 'John', ['email' => 'john@example.com']));
    
    // Test nested spread (note: this creates a nested array)
    var_dump(test_nested_spread());
    
    // Test string key spread (last value wins)
    var_dump(test_string_key_spread());
    
    // Test spread empty arrays
    var_dump(test_spread_empty());
    
    // Test complex real-world example
    $baseConfig = ['debug' => false, 'timeout' => 30];
    $envConfig = ['timeout' => 60, 'retries' => 3];
    $config = [...$baseConfig, ...$envConfig];
    var_dump($config);
}
?>
--EXPECT--
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
array(5) {
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
}
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
array(4) {
  ["a"]=>
  int(1)
  ["b"]=>
  int(2)
  ["c"]=>
  int(3)
  ["d"]=>
  int(4)
}
array(4) {
  ["id"]=>
  int(1)
  ["name"]=>
  string(4) "John"
  ["email"]=>
  string(16) "john@example.com"
  ["active"]=>
  bool(true)
}
array(5) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  array(2) {
    [0]=>
    int(7)
    [1]=>
    int(8)
  }
  [3]=>
  int(9)
  [4]=>
  int(10)
}
array(2) {
  ["status"]=>
  string(6) "active"
  ["role"]=>
  string(5) "admin"
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
array(3) {
  ["debug"]=>
  bool(false)
  ["timeout"]=>
  int(60)
  ["retries"]=>
  int(3)
}
