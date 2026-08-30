--TEST--
any
--FILE--
<?php
function main()
{
    $a = any(10);
    $b = any(4);
    echo var_dump($a/$b);
}
?>
--EXPECT--
float(2.5)
