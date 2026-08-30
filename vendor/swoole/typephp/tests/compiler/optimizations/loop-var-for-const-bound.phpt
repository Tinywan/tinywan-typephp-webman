--TEST--
Loop var optimizer: for counter with constant bound variable
--FILE--
<?php
function main(): void {
    $n = 20000;
    $hits = 0;

    for ($i = 0; $i < $n; $i++) {
        if ($i === 0 || $i === 19999) {
            $hits++;
        }
    }

    var_dump($i);
    var_dump($n);
    var_dump($hits);
}
?>
--EXPECT--
int(20000)
int(20000)
int(2)
