--TEST--
php_strip_whitespace() and output buffer
--SKIPIF--
<?php
echo 'skip AOT compilation or behavior differs';
?>
--FILE--
<?php
$file = str_repeat("A", PHP_MAXPATHLEN - strlen(__DIR__ . DIRECTORY_SEPARATOR . __FILE__));

var_dump(php_strip_whitespace($file));
var_dump(ob_get_contents());

?>
--EXPECTF--
Warning: php_strip_whitespace(%s): Failed to open stream: File%Sname too long in %s007.php on line %d
string(0) ""
bool(false)
