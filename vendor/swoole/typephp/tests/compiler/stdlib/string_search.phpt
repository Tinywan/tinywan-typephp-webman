--TEST--
strstr / strrpos / strpos / stripos
--FILE--
<?php
// strstr
var_dump(strstr("hello world", "world"));
var_dump(strstr("hello world", "world", true));
var_dump(strstr("hello world", "xyz"));

// strrpos
var_dump(strrpos("hello world hello", "hello"));
var_dump(strrpos("hello world hello", "hello", -5));
var_dump(strrpos("hello world", "xyz"));

// strpos
var_dump(strpos("hello world", "world"));
var_dump(strpos("hello world", "xyz"));

// stripos
var_dump(stripos("Hello World", "world"));
var_dump(stripos("Hello World", "xyz"));
?>
--EXPECT--
string(5) "world"
string(6) "hello "
bool(false)
int(12)
int(12)
bool(false)
int(6)
bool(false)
int(6)
bool(false)
