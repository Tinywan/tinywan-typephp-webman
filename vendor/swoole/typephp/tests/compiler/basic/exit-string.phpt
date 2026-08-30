--TEST--
die with string should print message and terminate without fatal error
--ENV--
USE_ZEND_ALLOC=0
--FILE--
<?php

function main() {
    die("done\n");
    echo "unreachable\n";
}
?>
--EXPECT--
done
