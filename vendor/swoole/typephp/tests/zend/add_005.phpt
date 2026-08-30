--TEST--
adding integers to doubles
--FILE--
<?php
ini_set('precision', '14');
$i = 75636;
$d = 2834681123.123123;

$c = $i + $d;
var_dump($c);

$c = $d + $i;
var_dump($c);

echo "Done\n";
?>
--EXPECT--
float(2834756759.123123)
float(2834756759.123123)
Done
