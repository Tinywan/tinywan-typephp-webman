--TEST--
foreach with list/array destructuring syntax
--FILE--
<?php

function main() {
    $array = [];
    $array[] = ['foo', 'elem1'];
    $array[] = ['bar', 'elem2'];
    foreach (array_reverse($array) as [$v1, $v2]) {
        var_dump($v1, $v2);
    }
}
?>
--EXPECT--
string(3) "bar"
string(5) "elem2"
string(3) "foo"
string(5) "elem1"
