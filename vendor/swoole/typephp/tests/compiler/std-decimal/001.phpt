--TEST--
std decimal: vector push_back and read
--FILE--
<?php

function main() {
    $v = std::vector(Type::Decimal);
    $v[] = 3.14;
    $v[] = 2.5;
    $v[] = 100;

    var_dump($v[0]->toString());
    var_dump($v[1]->toString());
    var_dump($v[2]->toString());
    var_dump(count($v));
}
?>
--EXPECT--
string(4) "3.14"
string(3) "2.5"
string(3) "100"
int(3)
