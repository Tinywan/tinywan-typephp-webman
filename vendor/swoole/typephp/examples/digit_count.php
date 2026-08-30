<?php
function digit_count(int $x)
{
    if ($x == 0) {
        return 1;
    }
    if ($x == PHP_INT_MAX) {
        return 20;
    }

    $count = 0;
    if ($x < 0) {
        ++$count;
        $x = -$x;
    }

    while ($x >= 1) {
        ++$count;
        $x /= 10;
    }
    return $count;
}

function main()
{
    $b = microtime( true);
    $n = 100_0000;
    $v = 123456789000;
    while($n --) {
        $r = digit_count($v);
//        $r = strlen(strval($v));
    }

    $e = microtime( true);
    echo "sec: " . ($e - $b) . "\n";
    var_dump($r);
}