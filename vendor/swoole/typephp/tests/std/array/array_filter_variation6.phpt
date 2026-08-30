--TEST--
Test array_filter() function : usage variations - 'input' array containing references
--SKIPIF--
<?php
if (true) die("skip AOT does not fully support references");
?>
--FILE--
<?php
/*
* Passing 'input' array which contains elements as reference to other data
*/

function callback($input)
{
  if($input > 5) {
    return true;
  }
  else {
    return false;
  }
}

function main() {
echo "*** Testing array_filter() : usage variations - 'input' containing references ***\n";

// initializing variables
$value1 = array(1, 2, 8);
$value2 = array(5, 6, 4);
$input = array(&$value1, 10, &$value2, 'value');

// with 'callback' argument
var_dump( array_filter($input, 'callback') );

// with default 'callback' argument
var_dump( array_filter($input) );

echo "Done";
}
?>
--EXPECT--
*** Testing array_filter() : usage variations - 'input' containing references ***
array(4) {
  [0]=>
  &array(3) {
    [0]=>
    int(1)
    [1]=>
    int(2)
    [2]=>
    int(8)
  }
  [1]=>
  int(10)
  [2]=>
  &array(3) {
    [0]=>
    int(5)
    [1]=>
    int(6)
    [2]=>
    int(4)
  }
  [3]=>
  string(5) "value"
}
array(4) {
  [0]=>
  &array(3) {
    [0]=>
    int(1)
    [1]=>
    int(2)
    [2]=>
    int(8)
  }
  [1]=>
  int(10)
  [2]=>
  &array(3) {
    [0]=>
    int(5)
    [1]=>
    int(6)
    [2]=>
    int(4)
  }
  [3]=>
  string(5) "value"
}
Done
