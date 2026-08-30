--TEST--
Decimal: divmod
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = std::decimal("10");
    $b = std::decimal("3");
    $r = $a->divmod($b);
    var_dump(std::decimal($r[0])->toString());
    var_dump(std::decimal($r[1])->toString());

    $c = std::decimal("17.5");
    $d = std::decimal("5.0");
    $r2 = $c->divmod($d);
    var_dump(std::decimal($r2[0])->toString());
    var_dump(std::decimal($r2[1])->toString());
}
?>
--EXPECT--
string(1) "3"
string(1) "1"
string(1) "3"
string(3) "2.5"
