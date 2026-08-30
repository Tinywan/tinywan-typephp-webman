--TEST--
Indirect method call by array - Invalid class name
--FILE--
<?php

$arr = array('a', 'b');
$arr();

?>
--EXPECTF--
Fatal error: Uncaught Error: Invalid callback a::b, class "a" not found in %s
Stack trace:
#0 {main}
  thrown in %s on line %d
