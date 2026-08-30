--TEST--
for statement
--FILE--
<?php
function main()
{
    $i = 100;
    $arr = [];
    for($i = 0; $i < 100; $i++) {
        $arr[] = $i;
    }
    var_dump(count($arr));
    echo $arr[49];
    echo "\n";
}
?>
--EXPECT--
int(100)
49