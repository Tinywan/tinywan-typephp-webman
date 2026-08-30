--TEST--
define accepts namespaced and non-identifier constant names like PHP
--FILE--
<?php

function main(): void
{
    define('Vendor\Package\VALUE', 42);
    define('1 non identifier', 'supported');

    var_dump(constant('Vendor\Package\VALUE'));
    var_dump(constant('1 non identifier'));
}
?>
--EXPECT--
int(42)
string(9) "supported"
