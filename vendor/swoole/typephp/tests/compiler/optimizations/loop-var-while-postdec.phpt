--TEST--
Loop var optimizer: while post-decrement from constant
--FILE--
<?php
function main(): void {
    $n = 1000;
    $sum = 0;

    while ($n--) {
        if ($n < 3) {
            $sum += $n;
        }
    }

    var_dump($n);
    var_dump($sum);
}
?>
--EXPECT--
int(-1)
int(3)
