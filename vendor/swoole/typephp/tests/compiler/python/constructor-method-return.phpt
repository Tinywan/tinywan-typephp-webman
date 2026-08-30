--TEST--
Python constructor-only programs preserve proxy results as PyObject
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
    $list = python\list([42]);
    $value = $list[0];
    var_dump(get_class($value));
    var_dump(python\scalar($value)->toInt());
}
?>
--EXPECT--
string(8) "PyObject"
int(42)
