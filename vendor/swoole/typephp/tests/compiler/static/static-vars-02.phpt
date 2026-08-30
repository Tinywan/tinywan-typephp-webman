--TEST--
static variables
--FILE--
<?php

function main() {
    $c = 100;
    static $arr3 = array($c => 'ten');
    print_r($arr3);
}
?>
--EXPECT--
Array
(
    [100] => ten
)