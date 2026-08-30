--TEST--
Arrays - Indexed, Associative, and Multidimensional
--FILE--
<?php
// Test indexed arrays
$indexed = [1, 2, 3, 4, 5];
var_dump($indexed);

// Test associative arrays
$assoc = [
    "name" => "John",
    "age" => 30,
    "city" => "New York"
];
var_dump($assoc);

// Test array access
$first_element = $indexed[0];
var_dump($first_element);

$name = $assoc["name"];
var_dump($name);

// Test array modification
$indexed[0] = 10;
var_dump($indexed[0]);

$assoc["age"] = 35;
var_dump($assoc["age"]);

// Test array functions
$numbers = [3, 1, 4, 1, 5, 9, 2, 6];
sort($numbers);
var_dump($numbers);

$assoc2 = [
    "product" => "Laptop",
    "price" => 999.99,
    "in_stock" => true
];

$keys = array_keys($assoc2);
var_dump($keys);

$values = array_values($assoc2);
var_dump($values);

// Test multidimensional array
$multi = [
    [1, 2, 3],
    [4, 5, 6],
    [7, 8, 9]
];
var_dump($multi);

// Access multidimensional array
$element = $multi[1][2]; // Should be 6
var_dump($element);

// Test array push
$stack = [];
array_push($stack, "first");
array_push($stack, "second");
array_push($stack, "third");
var_dump($stack);

// Test foreach loop with arrays
$output = [];
foreach ($indexed as $value) {
    $output[] = $value * 2;
}
var_dump($output);

$assoc_output = [];
foreach ($assoc as $key => $value) {
    $assoc_output[$key] = "Value: " . $value;
}
var_dump($assoc_output);
?>
--EXPECT--
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
array(3) {
  ["name"]=>
  string(4) "John"
  ["age"]=>
  int(30)
  ["city"]=>
  string(8) "New York"
}
int(1)
string(4) "John"
int(10)
int(35)
array(8) {
  [0]=>
  int(1)
  [1]=>
  int(1)
  [2]=>
  int(2)
  [3]=>
  int(3)
  [4]=>
  int(4)
  [5]=>
  int(5)
  [6]=>
  int(6)
  [7]=>
  int(9)
}
array(3) {
  [0]=>
  string(7) "product"
  [1]=>
  string(5) "price"
  [2]=>
  string(8) "in_stock"
}
array(3) {
  [0]=>
  string(6) "Laptop"
  [1]=>
  float(999.99)
  [2]=>
  bool(true)
}
array(3) {
  [0]=>
  array(3) {
    [0]=>
    int(1)
    [1]=>
    int(2)
    [2]=>
    int(3)
  }
  [1]=>
  array(3) {
    [0]=>
    int(4)
    [1]=>
    int(5)
    [2]=>
    int(6)
  }
  [2]=>
  array(3) {
    [0]=>
    int(7)
    [1]=>
    int(8)
    [2]=>
    int(9)
  }
}
int(6)
array(3) {
  [0]=>
  string(5) "first"
  [1]=>
  string(6) "second"
  [2]=>
  string(5) "third"
}
array(5) {
  [0]=>
  int(20)
  [1]=>
  int(4)
  [2]=>
  int(6)
  [3]=>
  int(8)
  [4]=>
  int(10)
}
array(3) {
  ["name"]=>
  string(11) "Value: John"
  ["age"]=>
  string(9) "Value: 35"
  ["city"]=>
  string(15) "Value: New York"
}
