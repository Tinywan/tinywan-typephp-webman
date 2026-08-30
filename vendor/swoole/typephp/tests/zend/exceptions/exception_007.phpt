--TEST--
Setting previous exception
--FILE--
<?php

try {
    try {
        throw new Exception("First", 1, new Exception("Another", 0, NULL));
    }
    catch (Exception $e) {
        throw new Exception("Second", 2, $e);
    }
}
catch (Exception $e) {
    throw new Exception("Third", 3, $e);
}

?>
--EXPECTF--
Fatal error: Uncaught Exception: Another in %s
Stack trace:
#0 {main}

Next Exception: First in %s
Stack trace:
#0 {main}

Next Exception: Second in %s
Stack trace:
#0 {main}

Next Exception: Third in %s
Stack trace:
#0 {main}
  thrown in %s
