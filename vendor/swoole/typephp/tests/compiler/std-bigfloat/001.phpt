--TEST--
std bigfloat: vector push_back and read
--FILE--
<?php

function main() {
    $v = std::vector(Type::BigFloat);
    $v[] = 3.14;
    $v[] = 2.71;
    $v[] = 100;

    var_dump($v[0]->toString());
    var_dump($v[1]->toString());
    var_dump($v[2]->toString());
    var_dump(count($v));
}
?>
--EXPECT--
string(18) "3.1400000000000001"
string(4) "2.71"
string(3) "100"
int(3)
