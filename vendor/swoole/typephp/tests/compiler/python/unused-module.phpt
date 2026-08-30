--TEST--
An unused Python module alias does not import the module
--FILE--
<?php
use python\module_that_does_not_exist;

function main(): void
{
    echo "no import\n";
}
?>
--EXPECT--
no import
