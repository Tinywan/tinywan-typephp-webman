--TEST--
std bigint: array write/read
--FILE--
<?php

function main() {
    $a = std::array(Type::BigInt, 5);
    $a[0] = 42;
    $a[1] = 84;
    $a[2] = 126;

    var_dump($a[0]->toString());
    var_dump($a[1]->toString());
    var_dump($a[2]->toString());
}
?>
--EXPECT--
string(2) "42"
string(2) "84"
string(3) "126"
