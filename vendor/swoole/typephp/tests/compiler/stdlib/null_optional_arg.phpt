--TEST--
Passing null to optional parameters should use C++ default values
--FILE--
<?php
error_reporting(E_ALL & ~E_DEPRECATED);

// substr: null length should take rest of string, not return empty
$s = 'hello world';
var_dump(substr($s, 6, null));
var_dump(substr($s, 6) === substr($s, 6, null));
var_dump(substr($s, 0, null) === $s);
var_dump(substr($s, null) === $s);

// strpos: null offset should default to 0
var_dump(strpos($s, 'o', null));
var_dump(strpos($s, 'o', null) === strpos($s, 'o'));

// stripos: null offset should default to 0
var_dump(stripos($s, 'O', null));
var_dump(stripos($s, 'O', null) === stripos($s, 'O'));

// strrpos: null offset should default to 0 (search from end)
var_dump(strrpos('hello hello', 'o', null));
var_dump(strrpos('hello hello', 'o', null) === strrpos('hello hello', 'o'));

// strstr: null before_needle should default to false
var_dump(strstr($s, 'o', null));
var_dump(strstr($s, 'o', null) === strstr($s, 'o'));

// str_repeat: ensure non-null still works
var_dump(str_repeat('ab', 3));

// explode with null limit: null coerces to 0 (limit=0: whole string as single element)
$arr = explode(' ', 'a b c d', null);
var_dump(count($arr));

?>
--EXPECT--
string(5) "world"
bool(true)
bool(true)
bool(true)
int(4)
bool(true)
int(4)
bool(true)
int(10)
bool(true)
string(7) "o world"
bool(true)
string(6) "ababab"
int(1)
