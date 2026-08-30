--TEST--
adding numbers to strings
--INI--
precision=14
--FILE--
<?php

$i = 75636;
$s1 = "this is a string";
$s2 = "876222numeric";
$s3 = "48474874";
$s4 = "25.68";

try {
    $c = $i + $s1;
    var_dump($c);
} catch (\TypeError $e) {
    echo $e->getMessage() . \PHP_EOL;
}
$c = $i + $s2;
var_dump($c);

$c = $i + $s3;
var_dump($c);

$c_float = $i + floatval($s4);
var_dump($c_float);

try {
    $c = $s1 + $i;
    var_dump($c);
} catch (\TypeError $e) {
    echo $e->getMessage() . \PHP_EOL;
}

$c = $s2 + $i;
var_dump($c);

$c = $s3 + $i;
var_dump($c);

$c_float3 = floatval($s4) + $i;
var_dump($c_float3);

echo "Done\n";
?>
--EXPECTF--
int(75636)
int(951858)
int(48550510)
float(75661.68)
int(75636)
int(951858)
int(48550510)
float(75661.68)
Done
