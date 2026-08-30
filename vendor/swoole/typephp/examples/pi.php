<?php
use native_types;

function main()
{
    ini_set("precision", 17);
    $rounds = 100000000;
    $stop = $rounds + 2;
    var_dump($stop);

    $begin = microtime(true);
    $x = 1.0;
    $pi = 1.0;

    for ($i = 2; $i <= $stop; $i++) {
        $x = -1.0 + 2.0 * ($i & 0x1);
        $pi += $x / (2 * $i - 1);
    }

    $pi *= 4.0;
    print $pi . "\ntime: " . (microtime(true) - $begin) . "\n";
}
