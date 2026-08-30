--TEST--
Python builtin names remain case-sensitive
--SKIPIF--
<?php
if (!extension_loaded('phpy')) {
    die('skip phpy extension is not loaded');
}
?>
--FILE--
<?php

function main(): void
{
    try {
        python\Len([1, 2, 3]);
    } catch (PyError $error) {
        echo "unknown builtin\n";
    }
}
?>
--EXPECT--
unknown builtin
