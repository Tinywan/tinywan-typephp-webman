--TEST--
decimal: literal
--FILE--
<?php
function main()
{
    require __DIR__ . '/../../../src/Assert.php';
    $a = std::decimal(3.1);
    $b = $a + 1.2;
    var_dump($b->toString());
}
?>
--EXPECT--
string(3) "4.3"
