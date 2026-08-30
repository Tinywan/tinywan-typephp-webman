--TEST--
Using a Python module without phpy raises a catchable PHP Error
--ENV--
PHPRC=tests/compiler/python/empty.ini
PHP_INI_SCAN_DIR={PWD}/empty-ini-dir
--FILE--
<?php
use python\math;

function main(): void
{
    try {
        var_dump(math\pi);
    } catch (Error $error) {
        echo get_class($error), "\n";
    }
}
?>
--EXPECT--
Error
