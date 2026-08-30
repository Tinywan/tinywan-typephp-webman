--TEST--
right associative
--FILE--
<?php
$a = $b = $c = 100;
var_dump($a);
var_dump($b);
var_dump($c);

$d[] = $e[] = $f[] = 23;
var_dump($d);
var_dump($e);
var_dump($f);

$h = $i = $j = new stdClass();
$h->val = $i->val = $j->val = 999;
var_dump($h);
var_dump($i);
var_dump($j);

$k['val'] = $l['val'] = $m['val'] = 888;
var_dump($k);
var_dump($l);
var_dump($m);
?>
--EXPECTF--
int(100)
int(100)
int(100)
array(1) {
  [0]=>
  int(23)
}
array(1) {
  [0]=>
  int(23)
}
array(1) {
  [0]=>
  int(23)
}
object(stdClass)#1 (1) {
  ["val"]=>
  int(999)
}
object(stdClass)#1 (1) {
  ["val"]=>
  int(999)
}
object(stdClass)#1 (1) {
  ["val"]=>
  int(999)
}
array(1) {
  ["val"]=>
  int(888)
}
array(1) {
  ["val"]=>
  int(888)
}
array(1) {
  ["val"]=>
  int(888)
}