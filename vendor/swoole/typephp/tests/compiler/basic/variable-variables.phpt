--TEST--
Variable variables ($$var)
--SKIPIF--
<?php
echo "skip The \$\$ syntax is not supported in AOT";
?>
--FILE--
<?php

$a = "hello";
$$a = "world";
var_dump($hello);

?>
--EXPECT--
string(5) "world"
