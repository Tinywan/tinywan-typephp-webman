--TEST--
std bigint: vector push_back and read
--FILE--
<?php

function main() {
    $v = std::vector(Type::BigInt);
    $v[] = 99;
    $v[] = 88;
    $v[] = 77;

    var_dump($v[0]->toString());
    var_dump($v[1]->toString());
    var_dump($v[2]->toString());
    var_dump(count($v));
}
?>
--EXPECT--
string(2) "99"
string(2) "88"
string(2) "77"
int(3)
