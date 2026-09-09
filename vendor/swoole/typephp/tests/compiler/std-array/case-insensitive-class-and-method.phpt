--TEST--
std class and method names, and the Type class name, are case-insensitive
--FILE--
<?php
function main(): void
{
    $values = StD::ArRaY(tYpE::Int, 2);
    $values[0] = 10;
    $values[1] = 20;

    var_dump($values[0], $values[1]);
    var_dump(\STD::BoOl(1));
}
?>
--EXPECT--
int(10)
int(20)
bool(true)
