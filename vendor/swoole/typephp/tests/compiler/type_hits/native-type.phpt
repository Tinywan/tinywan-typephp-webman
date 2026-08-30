--TEST--
native type
--FILE--
<?php
function main()
{
    $a = std::int(100);
    var_dump($a);

    $b = std::float(100.0);
    var_dump($b);

    $c = std::bool(true);
    var_dump($c);

    $a = 99;
    $d = std::int($a);
    var_dump($d);

    $e = '2026_';
    $f = std::float($e);
    var_dump($f);

    $h = 10;
    var_dump($h / 4);

    $i = std::int(10);
    var_dump($i / 4);

    $j = 2.5;
    var_dump($j * 4);
}
?>
--EXPECT--
int(100)
float(100)
bool(true)
int(99)
float(2026)
float(2.5)
int(2)
float(10)