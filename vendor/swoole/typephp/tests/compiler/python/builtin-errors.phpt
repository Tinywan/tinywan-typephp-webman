--TEST--
Python builtin and constructor errors preserve their runtime exception types
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
        python\len();
    } catch (PyError $error) {
        echo "python argument error\n";
    }

    try {
        python\list('not an array');
    } catch (Error $error) {
        echo "phpy constructor error\n";
    }
}
?>
--EXPECT--
python argument error
phpy constructor error
