--TEST--
π
--FILE--
<?php
function main()
{
    ini_set("precision", 17);
    $rounds = std::int(1_0000_0000);
    $stop = std::int($rounds + 2);

    $begin = microtime(true);
    $x = std::float(1.0);
    $pi = std::float(1.0);

    for ($i = std::int(2); $i <= $stop; $i++) {
        $x = -1.0 + 2.0 * ($i & 0x1);
        $pi += $x / (2 * $i - 1);
    }

    $pi *= 4.0;
    print $pi . "\n";
}
?>
--EXPECT--
3.141592643589326