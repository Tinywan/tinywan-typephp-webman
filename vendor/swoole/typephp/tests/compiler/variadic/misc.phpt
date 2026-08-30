--TEST--
Variadic Functions - Variable number of arguments with ...
--SKIPIF--
--FILE--
<?php

// Test combining variadic with type hints
function concat_strings(string ...$strings): string {
    return implode('', $strings);
}

// Test variadic returning array
function filter_positive(int ...$numbers): array {
    return array_filter($numbers, fn($n) => $n > 0);
}

// Test variadic with reference (should fail gracefully)
function test_by_reference(...$params) {
    foreach ($params as &$param) {
        $param *= 2;
    }
    return $params;
}

function main() {
    // Test combining variadic with type hints
    var_dump(concat_strings("Hello", " ", "World", "!"));
    var_dump(concat_strings("PHP", "AOT"));

    // Test variadic returning array
    var_dump(filter_positive(1, -2, 3, -4, 5));

    // Test variadic with reference
    $values = [1, 2, 3];
    var_dump(test_by_reference(...$values));
}
?>
--EXPECT--
string(12) "Hello World!"
string(6) "PHPAOT"
array(3) {
  [0]=>
  int(1)
  [2]=>
  int(3)
  [4]=>
  int(5)
}
array(3) {
  [0]=>
  int(2)
  [1]=>
  int(4)
  [2]=>
  int(6)
}
