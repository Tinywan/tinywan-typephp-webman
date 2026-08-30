--TEST--
Test ?? operator
--FILE--
<?php
function foo1() {
    $a = null;
    $b = null;
    $c = 100;
    $a ??= $b ??= $c;
    var_dump($a, $b);

}

function foo2() {
    $a = null;
    $b = 33;
    $c = 100;
    $a ??= $b ??= $c;
    var_dump($a, $b);
}

function main() {
    foo1();
    foo2();
}
?>
--EXPECT--
int(100)
int(100)
int(33)
int(33)