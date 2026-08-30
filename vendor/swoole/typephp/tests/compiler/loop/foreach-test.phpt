--TEST--
foreach statement
--FILE--
<?php
function main()
{
    $v = 199;
    $arr = range(0, 99);
    $c = 0;
    foreach ($arr as $_v) {
        $c += $_v;
    }
    var_dump($c);
    var_dump($v);
}
?>
--EXPECT--
int(4950)
int(199)
