--TEST--
var convert chained on array element
--FILE--
<?php
function main()
{
    $v = '0.1'->toDecimal();
    $r = $v + '0.2';
    echo $r;
}
?>
--EXPECT--
0.3
