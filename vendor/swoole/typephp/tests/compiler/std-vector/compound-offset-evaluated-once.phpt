--TEST--
std vector compound assignment evaluates its offset before the RHS
--FILE--
<?php
function main(): void
{
    $index = 0;
    $vector = std::vector(Type::Int, 2);
    $vector[0] = 10;
    $vector[1] = 20;

    $result = ($vector[$index] += ++$index);

    var_dump($index, $vector[0], $vector[1], $result);
}
?>
--EXPECT--
int(1)
int(11)
int(20)
int(11)
