--TEST--
foreach statement
--FILE--
<?php
function main()
{
    $arr = range(0, 5);
    foreach ($arr as &$_v) {
        $_v += 5;
    }
    echo json_encode($arr, JSON_PRETTY_PRINT), "\n";
}
?>
--EXPECT--
[
    5,
    6,
    7,
    8,
    9,
    10
]
