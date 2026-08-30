--TEST--
for loop with internal constant bound
--FILE--
<?php
$sum = 0;
for ($i = 0; $i < PHP_FD_SETSIZE; $i++) {
    $sum += $i & 1;
}
var_dump($sum);
?>
--EXPECT--
int(512)
