--TEST--
exit() accepts the TypePHP message named argument
--ENV--
USE_ZEND_ALLOC=0
--FILE--
<?php

function main(): void
{
    exit(message: "named exit\n");
    echo "unreachable\n";
}
?>
--EXPECT--
named exit
