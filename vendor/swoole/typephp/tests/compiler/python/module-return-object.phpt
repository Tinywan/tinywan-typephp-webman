--TEST--
Python module scalar results remain wrapped objects
--SKIPIF--
<?php
if (!extension_loaded('phpy')) {
    die('skip phpy extension is not loaded');
}
?>
--FILE--
<?php

use python\math;

function main(): void
{
    $result = math\sqrt(4);
    var_dump(get_class($result));
    var_dump(python\scalar($result)->toFloat());
}
?>
--EXPECT--
string(8) "PyObject"
float(2)
