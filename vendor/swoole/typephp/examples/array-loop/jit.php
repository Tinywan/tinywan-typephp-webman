<?php
$u = (int)$argv[1];
echo "u: $u\n";
$r = rand(0, 10000);
$a = array_fill(0, 10000, 0);

$begin = microtime(true);
for ($i = 0; $i < 10000; $i++) {
    for ($j = 0; $j < 100000; $j++) {
        $a[$i] += $j % $u;
    }
    $a[$i] += $r;
}

echo $a[$r] . "\n";
$end = microtime(true);
echo "sec: " . ($end - $begin) . "\n";
