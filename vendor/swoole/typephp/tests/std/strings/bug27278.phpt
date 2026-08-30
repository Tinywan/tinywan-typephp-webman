--TEST--
Bug #27278 (*printf() functions treat arguments as if passed by reference)
--SKIPIF--
<?php
echo 'skip AOT requires all executable code be in functions';
?>
--FILE--
<?php

function foo ($a)
{
    $a=sprintf("%02d",$a);
    var_dump($a);
}

$x="02";
var_dump($x);
foo($x);
var_dump($x);

?>
--EXPECT--
string(2) "02"
string(2) "02"
string(2) "02"
