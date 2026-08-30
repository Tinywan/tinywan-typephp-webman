--TEST--
Null coalescing (??) on unset reference variable
--FILE--
<?php

function main()
{
    // Test 1: unset reference variable then ?? with int
    $a = 1;
    $b = &$a;
    $b = 2;
    var_dump($a, $b);
    unset($b);
    var_dump($a);
    var_dump(isset($b));
    var_dump($b ?? 123);

    // Test 2: unset reference variable then ?? with string
    $c = 'hello';
    $d = &$c;
    $d = 'world';
    var_dump($c, $d);
    unset($d);
    var_dump($c);
    var_dump(isset($d));
    var_dump($d ?? 'default');

    // Test 3: ?? on reference without unset (should return value)
    $e = 42;
    $f = &$e;
    var_dump($f ?? 999);

    // Test 4: chained ?? with unset reference
    $g = 'first';
    $h = &$g;
    unset($h);
    var_dump($h ?? null ?? 'fallback');

    // Test 5: ??= on unset reference (assign coalesce)
    $i = 10;
    $j = &$i;
    $j = 20;
    unset($j);
    $j ??= 30;
    var_dump($j);
    var_dump($i);

    // Test 6: reference reassignment after unset
    $k = 100;
    $l = &$k;
    unset($l);
    var_dump($l ?? 200);
    $l = 300;
    var_dump($l);
    var_dump($k);

    // Test 7: normal variable unset + ??
    $m = 50;
    unset($m);
    var_dump($m ?? 777);

    // Test 8: isset after unset of reference
    $n = 'test';
    $o = &$n;
    unset($o);
    var_dump(isset($o));

    // Test 9: elvis (?:) on unset reference
    $p = 5;
    $q = &$p;
    $q = 6;
    unset($q);
    var_dump($q ?: -1);
}
?>
--EXPECT--
int(2)
int(2)
int(2)
bool(false)
int(123)
string(5) "world"
string(5) "world"
string(5) "world"
bool(false)
string(7) "default"
int(42)
string(8) "fallback"
int(30)
int(20)
int(200)
int(300)
int(100)
int(777)
bool(false)
int(-1)
