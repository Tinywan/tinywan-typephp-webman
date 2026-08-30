--TEST--
exit inside finally should terminate without graceful-exit fatal
--ENV--
USE_ZEND_ALLOC=0
--FILE--
<?php

try {
    echo "try\n";
} finally {
    echo "finally\n";
    exit(0);
}

echo "unreachable\n";
?>
--EXPECT--
try
finally
