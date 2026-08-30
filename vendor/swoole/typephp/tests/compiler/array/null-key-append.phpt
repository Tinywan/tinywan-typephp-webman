--TEST--
null array keys append while boolean keys use integer indexes
--FILE--
<?php
$nullKey = null;
$falseKey = false;
$trueKey = true;

$append = ['first'];
$append[$nullKey] = 'second';

$booleanKeys = [];
$booleanKeys[$falseKey] = 'zero';
$booleanKeys[$trueKey] = 'one';

var_dump($append, $booleanKeys);
?>
--EXPECT--
array(2) {
  [0]=>
  string(5) "first"
  [1]=>
  string(6) "second"
}
array(2) {
  [0]=>
  string(4) "zero"
  [1]=>
  string(3) "one"
}
