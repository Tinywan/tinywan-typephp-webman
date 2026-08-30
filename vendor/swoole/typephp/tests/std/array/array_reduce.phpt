--TEST--
Test array_reduce() function
--INI--
precision=14
--SKIPIF--
<?php die("skip AOT array_reduce type handling differs from PHP"); ?>
--FILE--
<?php
function reduce_int($w, $v)
{
    return $w + strlen($v);
}
function reduce_float($w, $v)
{
    return $w + strlen($v) / 10;
}
function reduce_string($w, $v)
{
    return $w . $v;
}
function reduce_array($w, $v)
{
    $w[$v]++;
    return $w;
}
function reduce_null($w, $v)
{
    return $w . $v;
}
function main()
{
    $array = array('foo', 'foo', 'bar', 'qux', 'qux', 'quux');
    echo "\n*** Testing array_reduce() to integer ***\n";
    $initial = 42;
    var_dump(array_reduce($array, 'reduce_int', $initial), $initial);
    echo "\n*** Testing array_reduce() to float ***\n";
    $initial = 4.2;
    var_dump(array_reduce($array, 'reduce_float', $initial), $initial);
    echo "\n*** Testing array_reduce() to string ***\n";
    $initial = 'quux';
    var_dump(array_reduce($array, 'reduce_string', $initial), $initial);
    echo "\n*** Testing array_reduce() to array ***\n";
    $initial = array('foo' => 42, 'bar' => 17, 'qux' => -2, 'quux' => 0);
    var_dump(array_reduce($array, 'reduce_array', $initial), $initial);
    echo "\n*** Testing array_reduce() to null ***\n";
    $initial = null;
    var_dump(array_reduce($array, 'reduce_null', $initial), $initial);
    echo "\nDone";
}
?>
--EXPECT--
*** Testing array_reduce() to integer ***
int(61)
int(42)

*** Testing array_reduce() to float ***
float(6.1)
float(4.2)

*** Testing array_reduce() to string ***
string(23) "quuxfoofoobarquxquxquux"
string(4) "quux"

*** Testing array_reduce() to array ***
array(4) {
  ["foo"]=>
  int(44)
  ["bar"]=>
  int(18)
  ["qux"]=>
  int(0)
  ["quux"]=>
  int(1)
}
array(4) {
  ["foo"]=>
  int(42)
  ["bar"]=>
  int(17)
  ["qux"]=>
  int(-2)
  ["quux"]=>
  int(0)
}

*** Testing array_reduce() to null ***
string(19) "foofoobarquxquxquux"
NULL

Done
