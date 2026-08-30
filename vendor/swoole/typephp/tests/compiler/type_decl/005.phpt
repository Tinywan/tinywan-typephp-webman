--TEST--
Type Declarations
--FILE--
<?php
function sum_iterable($numbers): mixed {
}

function main() {
    sum_iterable([1, 2, 3, 4, 5]);
    var_dump(__FUNCTION__);
}
?>
--EXPECT--
string(4) "main"
