--TEST--
Freeing of function "name" when dynamic call fails
--FILE--
<?php

try {
    $bar = "bar";
    ("foo" . $bar)();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    $bar = ["bar"];
    (["foo"] + $bar)();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}
try {
    (new stdClass)();
} catch (Error $e) {
    echo $e->getMessage(), "\n";
}

?>
--EXPECT--
Invalid callback foobar, function "foobar" not found or invalid function name

Warning: Array to string conversion in Unknown on line 0

Fatal error: Invalid callback Array, array callback must have exactly two members in Unknown on line 0

Fatal error: Invalid callback stdClass::__invoke, no array or string given in Unknown on line 0