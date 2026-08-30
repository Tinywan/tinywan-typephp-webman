--TEST--
foreach with list destructuring on Traversable object
--FILE--
<?php
function main() {
    $array = new ArrayObject();
    $array[] = ['foo', 'elem1'];
    $array[] = ['bar', 'elem2'];
    foreach ($array as [$v1, $v2]) {
        var_dump($v1, $v2);
    }
}
?>
--EXPECT--
string(3) "foo"
string(5) "elem1"
string(3) "bar"
string(5) "elem2"
