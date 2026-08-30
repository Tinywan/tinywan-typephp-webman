--TEST--
Loop var optimizer: for counter with typed int function bound
--FILE--
<?php
function limit_value(): int {
    return 5;
}

function main(): void {
    $last = -1;

    for ($i = 0; $i < limit_value(); $i++) {
        $last = $i;
    }

    var_dump($i);
    var_dump($last);
}
?>
--EXPECT--
int(5)
int(4)
