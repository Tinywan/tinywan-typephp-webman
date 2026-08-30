--TEST--
std containers allow non-structural element updates during foreach
--FILE--
<?php

function main(): void
{
    $vector = std::vector(Type::Int);
    $vector[] = 1;
    $vector[] = 2;
    foreach ($vector as $vectorKey => $vectorValue) {
        $vector[$vectorKey] += 10;
    }
    var_dump($vector[0], $vector[1]);

    $map = std::ordered_map(Type::String, Type::Int);
    $map['a'] = 3;
    $map['b'] = 4;
    foreach ($map as $mapKey => $mapValue) {
        $map[$mapKey] += 20;
    }
    var_dump($map['a'], $map['b']);
}
?>
--EXPECT--
int(11)
int(12)
int(23)
int(24)
