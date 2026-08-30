--TEST--
exit inside catch should not rethrow graceful exit as Throwable
--ENV--
USE_ZEND_ALLOC=0
--FILE--
<?php

try {
    throw new RuntimeException('stop');
} catch (Throwable $e) {
    exit(0);
}

echo "unreachable\n";
?>
--EXPECT--
