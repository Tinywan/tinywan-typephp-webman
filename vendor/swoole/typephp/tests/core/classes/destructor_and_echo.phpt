--TEST--
ZE2 Destructors and echo
--FILE--
<?php

class Test
{
    function __construct() {
        echo __METHOD__ . "\n";
    }

    function __destruct() {
        echo __METHOD__ . "\n";
    }
}

function main() {
    $o = new Test;
}

?>
--EXPECT--
Test::__construct
Test::__destruct
