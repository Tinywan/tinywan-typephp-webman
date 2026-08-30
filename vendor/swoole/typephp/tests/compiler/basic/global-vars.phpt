--TEST--
global vars
--FILE--
<?php
global $a;
$a = 100;

var_dump($GLOBALS['a']);
var_dump($a);
var_dump(gettype($_SERVER));
var_dump($_SERVER['argc']);

parse_str('hello=world&lang=php', $GLOBALS['query']);
var_dump($GLOBALS['query']);

$key = 'argc';
var_dump($GLOBALS[$key]);
?>
--EXPECTF--
int(100)
int(100)
string(5) "array"
int(%d)
array(2) {
  ["hello"]=>
  string(5) "world"
  ["lang"]=>
  string(3) "php"
}
int(%d)