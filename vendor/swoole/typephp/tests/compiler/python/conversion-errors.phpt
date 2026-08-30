--TEST--
Python conversion rejects invalid UTF-8 and recursive PHP arrays safely
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
        python\repr("\xff");
    } catch (PyError $error) {
        echo "invalid utf8\n";
    }

    $recursive = [];
    $recursive['self'] = &$recursive;
    try {
        python\repr($recursive);
    } catch (PyError $error) {
        echo "recursive php array\n";
    }
}
?>
--EXPECT--
invalid utf8
recursive php array
