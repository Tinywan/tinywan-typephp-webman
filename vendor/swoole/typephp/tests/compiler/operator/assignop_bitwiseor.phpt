--TEST--
Operator: AssignOp BitwiseOr
--FILE--
<?php
function main()
{
    $a =  100;
    $a |= 16;
    var_dump($a);

    $b = std::int(200);
    $b |= std::int(32);
    var_dump($b);
}
?>
--EXPECT--
int(116)
int(232)
