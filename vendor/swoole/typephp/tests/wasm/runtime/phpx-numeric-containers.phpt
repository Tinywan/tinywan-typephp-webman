--TEST--
WASM PHPX archive links high precision types and std containers
--FILE--
<?php
use native_types;

function main(): void
{
    $integer = std::bigInt('12345678901234567890');
    $decimal = std::decimal('10.25');
    $float = std::bigFloat('100000000000000000000');

    echo ($integer + 10)->toString(), "\n";
    echo ($decimal * 4)->toString(), "\n";
    echo ($float + std::bigFloat('1'))->toString(), "\n";

    $array = std::array(Type::Int, 2);
    $array[0] = 7;
    $vector = std::vector(Type::String);
    $vector[] = 'wasm';
    $map = std::map(Type::String, Type::Int);
    $map['answer'] = 42;
    $ordered = std::ordered_map(Type::String, Type::Int);
    $ordered['first'] = 1;

    echo $array[0], '|', $vector[0], '|', $map['answer'], '|', $ordered['first'], "\n";
}
?>
--EXPECT--
12345678901234567900
41.00
100000000000000000001
7|wasm|42|1
