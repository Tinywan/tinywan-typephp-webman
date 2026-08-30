--TEST--
Big numeric unary plus and boolean contexts use numeric truth values
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $bi0 = std::bigInt(0);
    $bi1 = std::bigInt(1);
    echo (+$bi1)->toString(), "\n";
    var_dump(!$bi0, !$bi1);
    var_dump($bi0 && $bi1, $bi0 || $bi1, ($bi1 xor $bi1), ($bi0 xor $bi1));

    $dec0 = std::decimal("0.0");
    $dec1 = std::decimal("1.0");
    echo (+$dec1)->toString(), "\n";
    var_dump(!$dec0, !$dec1);
    var_dump($dec0 && $dec1, $dec0 || $dec1, ($dec1 xor $dec1), ($dec0 xor $dec1));

    $bf0 = std::bigFloat("0");
    $bf1 = std::bigFloat("1");
    echo (+$bf1)->toString(), "\n";
    var_dump(!$bf0, !$bf1);
    var_dump($bf0 && $bf1, $bf0 || $bf1, ($bf1 xor $bf1), ($bf0 xor $bf1));

    echo $bi0 ? "bad-if\n" : "if-ok\n";
    echo $dec0 ? "bad-ternary\n" : "ternary-ok\n";
    echo ($bf0 ?: std::bigFloat("9"))->toString(), "\n";
    var_dump(empty($bi0), empty($bi1));

    while ($bi0) {
        echo "bad-while\n";
    }
    for (; $dec0;) {
        echo "bad-for\n";
    }
    do {
        echo "do-once\n";
    } while ($bf0);
}
?>
--EXPECT--
1
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(true)
1.0
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(true)
1
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(true)
if-ok
ternary-ok
9
bool(true)
bool(false)
do-once
