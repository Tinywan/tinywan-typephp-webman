--TEST--
Bug #29253 (array_diff with $GLOBALS argument fails)
--SKIPIF--
<?php
if (true) die("skip AOT does not support undefined variables");
?>

--FILE--
<?php
$zz = $GLOBALS;
$gg = 'afad';
var_dump(@array_diff_assoc($GLOBALS, $zz));
var_dump($gg);
?>
--EXPECTF--
array(2) {
  ["zz"]=>
  %A
  ["gg"]=>
  string(4) "afad"
}
string(4) "afad"
