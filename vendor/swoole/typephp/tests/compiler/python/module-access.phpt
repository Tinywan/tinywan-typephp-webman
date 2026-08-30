--TEST--
Python module aliases lazily import through phpy
--SKIPIF--
<?php
if (!extension_loaded('phpy')) {
    die('skip phpy extension is not loaded');
}
?>
--FILE--
<?php
use Python\math;

function main(): void
{
    var_dump(python\scalar(math\pi)->toFloat() > 3.14);
}
?>
--EXPECT--
bool(true)
