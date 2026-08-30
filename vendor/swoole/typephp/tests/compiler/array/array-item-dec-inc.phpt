--TEST--
array item inc/dec
--FILE--
<?php
function main()
{
    $array = array(1000);
    var_dump($array[0]);
    $array[0]--;
    var_dump($array[0]);
    $array[0]++;
    var_dump($array[0]);
}
?>
--EXPECT--
int(1000)
int(999)
int(1000)
