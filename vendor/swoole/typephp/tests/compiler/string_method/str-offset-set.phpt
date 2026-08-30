--TEST--
string offset set
--FILE--
<?php
$s1 = "hello world";
$s2 = "php";
$s1[1] = $s2[1] = '_';
$last = -1;
$s1[$last] = '!';
var_dump($s1, $s2);
?>
--EXPECT--
string(11) "h_llo worl!"
string(3) "p_p"
