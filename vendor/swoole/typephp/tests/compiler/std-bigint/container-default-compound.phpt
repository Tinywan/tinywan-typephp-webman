--TEST--
std high precision containers: defaults, compound writes and unset
--FILE--
<?php
function inspect_bigint_array($source): void {
    $alias = $source->toStdArray(Type::BigInt, 2);
    var_dump($alias[0]->toString());
}

function main() {
    $integers = std::array(Type::BigInt, 2);
    var_dump($integers[0]->toString());
    $integers[0] += 5;
    var_dump($integers[0]->toString());
    inspect_bigint_array($integers);
    unset($integers[0]);
    var_dump($integers[0]->toString());

    $floats = std::vector(Type::BigFloat, 1);
    $floats[0] += 2;
    var_dump($floats[0]->toString());
    unset($floats[0]);
    var_dump($floats[0]->toString());

    $decimals = std::map(Type::String, Type::Decimal);
    $decimals['total'] += 3;
    var_dump($decimals['total']->toString());
}
?>
--EXPECT--
string(1) "0"
string(1) "5"
string(1) "5"
string(1) "0"
string(1) "2"
string(1) "0"
string(1) "3"
