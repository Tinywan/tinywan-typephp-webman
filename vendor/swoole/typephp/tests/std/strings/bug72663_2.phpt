--TEST--
Bug #72663: Create an Unexpected Object and Don't Invoke __wakeup() in Deserialization
--SKIPIF--
<?php
echo 'skip session functions cannot be used after headers sent in AOT auto-wrap';
?>
--FILE--
<?php

ini_set('session.serialize_handler', 'php_serialize');
session_start();
$sess = 'O:9:"Exception":2:{s:7:"'."\0".'*'."\0".'file";s:0:"";}';
session_decode($sess);
var_dump($_SESSION);
echo "DONE\n";
?>
--EXPECTF--
Warning: session_decode(): Unexpected end of serialized data in %s on line %d

Warning: session_decode(): Failed to decode session object. Session has been destroyed in %s on line %d
array(0) {
}
DONE
