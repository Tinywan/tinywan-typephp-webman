--TEST--
global vars
--FILE--
<?php
global $a;
$a = 100;

global $b;
$b = "rango";

require __DIR__ . '/../../../src/Assert.php';

Assert::eq(gettype($GLOBALS), 'array');
Assert::greaterThan(count($GLOBALS), 6);
?>
--EXPECTF--
