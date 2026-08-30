--TEST--
urlencode / urldecode / rawurlencode / rawurldecode edge cases
--FILE--
<?php
// Empty string
var_dump(urlencode(""));
var_dump(urldecode(""));
var_dump(rawurlencode(""));
var_dump(rawurldecode(""));

// Basic encoding
var_dump(urlencode("hello world"));
var_dump(urldecode("hello+world"));
var_dump(rawurlencode("hello world"));
var_dump(rawurldecode("hello%20world"));

// Special characters
var_dump(urlencode("äöü"));
var_dump(rawurlencode("äöü"));

// Reserved characters: + and = are encoded the same by both functions
var_dump(urlencode("a+b=c"));
var_dump(rawurlencode("a+b=c"));

// Round-trip
$orig = "Hello World! @#$%^&*()";
var_dump(urldecode(urlencode($orig)) === $orig);
var_dump(rawurldecode(rawurlencode($orig)) === $orig);
?>
--EXPECT--
string(0) ""
string(0) ""
string(0) ""
string(0) ""
string(11) "hello+world"
string(11) "hello world"
string(13) "hello%20world"
string(11) "hello world"
string(18) "%C3%A4%C3%B6%C3%BC"
string(18) "%C3%A4%C3%B6%C3%BC"
string(9) "a%2Bb%3Dc"
string(9) "a%2Bb%3Dc"
bool(true)
bool(true)
