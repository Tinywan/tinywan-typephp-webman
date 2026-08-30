<?php
function issetor(int $n)
{
    $val = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9);
    for ($i = 0; $i < $n; ++$i) {
        $x = $val ?: null;
    }
}

function main()
{
    $s = microtime( true);
    issetor(1000_0000);
    echo microtime( true) - $s . "\n";
}