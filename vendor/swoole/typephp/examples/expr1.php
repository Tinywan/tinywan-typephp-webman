<?php
function expr1()
{
    for ($a = 2, $b = 4; $a < 3; $a++) {
        echo $a . "\n";
        echo $b . "\n";
    }

    $a = random_int(128, 512) % 100 > 50 ? 1 : 2;
    $c = $a ** $b;
    $c--;
    --$c;

    $d = $a & $b;
    $d = $a | $b;
    $d = $a ^ $b;
    $d = ~$a;
    $d = $a << $b;
    $d = $a >> $b;

    if ($a == $b) {
        echo "[1] Equal\n";
    } else {
        echo "[1] Not equal\n";
    }

    if ($a != $b) {
        echo "Not equal\n";
    }

    if ($a and $b) {
        echo "And\n";
    }

    if ($a or $b) {
        echo "Or\n";
    }

    if ($a xor $b) {
        echo "Xor\n";
    }

    if ($a && $b) {
        echo "Logical And\n";
    }

    if ($a || $b) {
        echo "Logical Or\n";
    }

    if (!$a) {
        echo "Not\n";
    }

    if ($a > 1000) {
        echo "Greater than 1000\n";
    } elseif ($a > 500) {
        echo "Greater than 500\n";
    } else if ($a > 100) {
        echo "Greater than 100\n";
    } else {
        echo "Less to 100\n";
    }

    $i = 1;
    while ($i <= 10) {
        echo $i++;
    }

    $i = 1;
    while ($i <= 10):
        print $i;
        $i++;
    endwhile;

    $i = 10;
    do {
        echo $i;
        $i--;
    } while ($i > 0);

    $i = 1;
    for (; ; $i++) {
        if ($i > 10) {
            break;
        }
        if (0 === ($i % 5)) {
            continue;
        }
        echo $i;
    }

    return $a;
}
