<?php
function fib(int $n): int
{
    if ($n == 1 || $n == 2) {
        return 1;
    } else {
        return fib($n - 1) + fib($n - 2);
    }
}

function main()
{
    global $argv;
    $n = $argv[2];
    var_dump($argv, $n);

    if ($n > 100) {
        echo "Too big number\n";
        exit(1);
    } elseif ($n < 1) {
        echo "Too small number\n";
        exit(1);
    }

    $begin = microtime(true);
    echo fib($n) . "\n";
    echo "Time: " . (microtime(true) - $begin) . "\n";
}
