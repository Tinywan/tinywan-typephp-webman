--TEST--
exit(0) should terminate without fatal error
--ENV--
USE_ZEND_ALLOC=0
--FILE--
<?php

function main() {
    exit(0);
    echo "unreachable\n";
}
?>
--EXPECT--
