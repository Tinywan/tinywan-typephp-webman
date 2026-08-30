--TEST--
Array 007
--FILE--
<?php
$bool_values = array (true => true, false => false, TRUE => TRUE, FALSE => FALSE);
$temp_array = $bool_values;
var_dump(krsort($temp_array) );
var_dump($temp_array);
?>
--EXPECT--
bool(true)
array(2) {
  [1]=>
  bool(true)
  [0]=>
  bool(false)
}