--TEST--
Loop var optimizer: descending for counter with typed int function init
--FILE--
<?php
function start_value(): int {
    return 4;
}

function main(): void {
    $sum = 0;

    for ($i = start_value(); $i > 0; $i--) {
        $sum += $i;
    }

    var_dump($i);
    var_dump($sum);
}
?>
--EXPECT--
int(0)
int(10)
