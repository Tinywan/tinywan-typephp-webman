--TEST--
Bug #62607: array_walk_recursive move internal pointer
--SKIPIF--
<?php
if (true) die("skip AOT does not support reference parameters in closures");
?>

--FILE--
<?php
$arr = array('a'=>'b');
echo 'Before -> '.current($arr).PHP_EOL;
array_walk_recursive($arr, function(&$val){});
echo 'After -> '.current($arr);
?>
--EXPECT--
Before -> b
After -> b
