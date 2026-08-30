--TEST--
Indirect method call by array - Invalid method name
--FILE--
<?php

$arr = array('stdclass', 'b');
$arr();

?>
--EXPECTF--
Fatal error: Uncaught Error: Invalid callback stdclass::b, class stdClass does not have a method "b" in %s
Stack trace:
#0 {main}
  thrown in %s on line %d
