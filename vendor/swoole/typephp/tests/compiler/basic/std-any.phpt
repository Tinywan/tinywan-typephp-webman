--TEST--
any
--FILE--
<?php
function main()
{
    $ref = std::any();
    var_dump($ref);
    $ref = 'initialized';
    var_dump($ref);

    $a = std::any(10);
    $b = std::any(4);
    echo var_dump($a/$b);
}
?>
--EXPECT--
NULL
string(11) "initialized"
float(2.5)
