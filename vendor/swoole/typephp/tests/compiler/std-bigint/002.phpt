--TEST--
std bigint: map set/get
--FILE--
<?php

function main() {
    $m = std::map(Type::Int, Type::BigInt);
    $m[1] = 100;
    $m[2] = 200;
    $m[3] = 300;

    var_dump($m[1]->toString());
    var_dump($m[2]->toString());
    var_dump($m[3]->toString());
    var_dump(count($m));
}
?>
--EXPECT--
string(3) "100"
string(3) "200"
string(3) "300"
int(3)
