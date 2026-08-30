--TEST--
Bug #30074 (EG(uninitialized_zval_ptr) gets set to reference using EXTR_REFS, affecting later values)
--SKIPIF--
<?php
if (true) die("skip AOT does not support extract()");
?>

--FILE--
<?php
$result = extract(array('a'=>$undefined), EXTR_REFS);
var_dump(array($a));
echo "Done\n";
?>
--EXPECTF--
Warning: Undefined variable $undefined in %s on line %d
array(1) {
  [0]=>
  NULL
}
Done
