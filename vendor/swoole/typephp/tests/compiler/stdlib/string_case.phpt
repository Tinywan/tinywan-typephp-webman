--TEST--
lcfirst / ucfirst / strtolower / strtoupper edge cases
--FILE--
<?php
// lcfirst
var_dump(lcfirst(""));
var_dump(lcfirst("Hello"));
var_dump(lcfirst("HELLO"));
var_dump(lcfirst("hELLO"));

// ucfirst
var_dump(ucfirst(""));
var_dump(ucfirst("hello"));
var_dump(ucfirst("Hello"));
var_dump(ucfirst("hELLO"));

// strtolower
var_dump(strtolower(""));
var_dump(strtolower("HELLO"));
var_dump(strtolower("Hello World"));

// strtoupper
var_dump(strtoupper(""));
var_dump(strtoupper("hello"));
var_dump(strtoupper("Hello World"));
?>
--EXPECT--
string(0) ""
string(5) "hello"
string(5) "hELLO"
string(5) "hELLO"
string(0) ""
string(5) "Hello"
string(5) "Hello"
string(5) "HELLO"
string(0) ""
string(5) "hello"
string(11) "hello world"
string(0) ""
string(5) "HELLO"
string(11) "HELLO WORLD"
