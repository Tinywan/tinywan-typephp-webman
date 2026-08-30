--TEST--
BigInt: divmod
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = std::bigInt(10);
    $b = std::bigInt(3);
    $r = $a->divmod($b);
    var_dump(std::bigInt($r[0])->toString());
    var_dump(std::bigInt($r[1])->toString());

    $c = std::bigInt(100);
    $d = std::bigInt(7);
    $r2 = $c->divmod($d);
    var_dump(std::bigInt($r2[0])->toString());
    var_dump(std::bigInt($r2[1])->toString());

    // negative dividend
    $e = std::bigInt(-10);
    $f = std::bigInt(3);
    $r3 = $e->divmod($f);
    var_dump(std::bigInt($r3[0])->toString());
    var_dump(std::bigInt($r3[1])->toString());
}
?>
--EXPECT--
string(1) "3"
string(1) "1"
string(2) "14"
string(1) "2"
string(2) "-3"
string(2) "-1"
