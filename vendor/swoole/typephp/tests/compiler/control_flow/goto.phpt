--TEST--
Goto and label statements
--FILE--
<?php

function main() {
    $result = 0;

    // Forward goto
    goto forward;
    $result += 100; // skipped
    forward:
    $result += 1;

    // Backward goto
    $i = 0;
    loop_start:
    $i++;
    if ($i < 10) {
        goto loop_start;
    }
    $result += $i;

    // Goto out of nested structure
    $found = false;
    for ($j = 0; $j < 5; $j++) {
        for ($k = 0; $k < 5; $k++) {
            if ($j == 3 && $k == 2) {
                $found = true;
                goto found_label;
            }
        }
    }
    found_label:
    $result += ($found ? 10 : 0);

    var_dump($result);
}

?>
--EXPECT--
int(21)
