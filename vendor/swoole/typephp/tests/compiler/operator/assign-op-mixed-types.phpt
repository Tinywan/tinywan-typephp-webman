--TEST--
Compound assignment operators with mixed Var/explicit std native types
--FILE--
<?php

function main() {
    // ===== Var += various RHS types =====
    // Var += Int (no native types — Var operator should handle coercion)
    $a = 10;
    $a += 5;
    var_dump($a);

    // Var += Float
    $b = 10;
    $b += 2.5;
    var_dump($b);

    // Var += String (numeric)
    $c = 10;
    $c += "5.5";
    var_dump($c);

    // Var -= Int
    $d = 10;
    $d -= 3;
    var_dump($d);

    // Var *= Float
    $e = 10;
    $e *= 2.5;
    var_dump($e);

    // Var /= Int (integer division → float in PHP)
    $f = 10;
    $f /= 3;
    var_dump($f);

    // Var %= Int
    $g = 10;
    $g %= 3;
    var_dump($g);

    // ===== Explicit std::* native types without use native_types =====
    // Int += Int
    $h = std::int(100);
    $h += 50;
    var_dump($h);

    // Int += Float (native types: truncates)
    $i = std::int(100);
    $i += 3.7;
    var_dump($i);

    // Float += Int
    $j = std::float(10.5);
    $j += 5;
    var_dump($j);

    // Float += Float
    $k = std::float(10.5);
    $k += 3.25;
    var_dump($k);

    // Int -= Float
    $l = std::int(10);
    $l -= 3.5;
    var_dump($l);

    // Float -= Int
    $m = std::float(10.0);
    $m -= 3;
    var_dump($m);

    // Int *= Float
    $n = std::int(5);
    $n *= 2.5;
    var_dump($n);

    // Float *= Int
    $o = std::float(3.0);
    $o *= 4;
    var_dump($o);

    // Int /= Int (native Int / Int → Int truncation)
    $p = std::int(10);
    $p /= 3;
    var_dump($p);

    // Float /= Int
    $q = std::float(10.0);
    $q /= 3;
    var_dump($q);

    // Int %= Int
    $r = std::int(10);
    $r %= 3;
    var_dump($r);

    echo "done\n";
}
?>
--EXPECT--
int(15)
float(12.5)
float(15.5)
int(7)
float(25)
float(3.3333333333333335)
int(1)
int(150)
int(103)
float(15.5)
float(13.75)
int(6)
float(7)
int(12)
float(12)
int(3)
float(3.3333333333333335)
int(1)
done
