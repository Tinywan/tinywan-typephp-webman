--TEST--
Testing throw exception doesn't crash with wrong params, variant 4
--FILE--
<?php

throw new Error(new stdClass);

?>
--EXPECTF--
Fatal error: Uncaught TypeError: Error::__construct(): Argument #1 ($message) must be of type string, stdClass given in %s:%d
Stack trace:
#0 %s: Error->__construct(Object(stdClass))
#1 {main}
  thrown in %s on line %d
