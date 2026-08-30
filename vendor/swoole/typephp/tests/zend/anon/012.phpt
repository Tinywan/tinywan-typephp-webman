--TEST--
Ensure correct unmangling of private property names for anonymous class instances
--FILE--
<?php
var_dump(new class { private $foo; });
?>
--EXPECTF--
object(_anon_class_%s)#1 (1) {
  ["foo":"_anon_class_%s":private]=>
  NULL
}
