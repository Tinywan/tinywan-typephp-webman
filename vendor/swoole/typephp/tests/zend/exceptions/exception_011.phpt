--TEST--
Test exception doesn't cause RSHUTDOWN bypass, variation 0
--INI--
zend.assertions=1
--FILE--
<?php

define ("XXXXX", 1);
assert(false);

?>
--EXPECTF--
Fatal error: Uncaught AssertionError %s
Stack trace:
#0 [internal function]: assert(false)
#1 {main}
  thrown in %s
