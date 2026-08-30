--TEST--
is_* type check functions
--FILE--
<?php
// is_array
var_dump(is_array([]));
var_dump(is_array([1,2,3]));
var_dump(is_array("hello"));
var_dump(is_array(42));

// is_string
var_dump(is_string(""));
var_dump(is_string("hello"));
var_dump(is_string(42));
var_dump(is_string([]));

// is_object
var_dump(is_object(new stdClass()));
var_dump(is_object([]));
var_dump(is_object(null));

// is_numeric
var_dump(is_numeric(42));
var_dump(is_numeric(3.14));
var_dump(is_numeric("42"));
var_dump(is_numeric("3.14"));
var_dump(is_numeric("hello"));
var_dump(is_numeric([]));

// is_scalar
var_dump(is_scalar(42));
var_dump(is_scalar("hello"));
var_dump(is_scalar(3.14));
var_dump(is_scalar(true));
var_dump(is_scalar([]));
var_dump(is_scalar(null));

// is_resource
$f = fopen(__FILE__, "r");
var_dump(is_resource($f));
fclose($f);
var_dump(is_resource(null));
var_dump(is_resource(42));
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(false)
bool(true)
bool(true)
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)
bool(true)
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(false)
