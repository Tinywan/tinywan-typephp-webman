--TEST--
User-defined and Built-in Functions
--FILE--
<?php
// Test user-defined function
function add($a, $b) {
    return $a + $b;
}

function multiply($x, $y) {
    return $x * $y;
}

function greet($name) {
    return "Hello, " . $name . "!";
}

function main() {
    // Test function calls
    $result1 = add(5, 3);
    var_dump($result1);

    $result2 = multiply(4, 7);
    var_dump($result2);

    $result3 = greet("World");
    var_dump($result3);

    // Test built-in functions
    $test_string = "  Hello World  ";
    $trimmed = trim($test_string);
    var_dump($trimmed);

    $upper = strtoupper($test_string);
    var_dump($upper);

    $len = strlen($trimmed);
    var_dump($len);

    // Test array functions
    $numbers = [1, 2, 3, 4, 5];
    $reversed = array_reverse($numbers);
    var_dump($reversed);

    $count = count($numbers);
    var_dump($count);
    $fact5 = factorial(5);
    var_dump($fact5);

    $greeting1 = greet_with_title("Smith");
    var_dump($greeting1);

    $greeting2 = greet_with_title("Jane", "Ms.");
    var_dump($greeting2);
}

// Test recursive function
function factorial($n) {
    if ($n <= 1) {
        return 1;
    }
    return $n * factorial($n - 1);
}

// Test function with default parameters
function greet_with_title($name, $title = "Mr.") {
    return $title . " " . $name;
}

?>
--EXPECT--
int(8)
int(28)
string(13) "Hello, World!"
string(11) "Hello World"
string(15) "  HELLO WORLD  "
int(11)
array(5) {
  [0]=>
  int(5)
  [1]=>
  int(4)
  [2]=>
  int(3)
  [3]=>
  int(2)
  [4]=>
  int(1)
}
int(5)
int(120)
string(9) "Mr. Smith"
string(8) "Ms. Jane"