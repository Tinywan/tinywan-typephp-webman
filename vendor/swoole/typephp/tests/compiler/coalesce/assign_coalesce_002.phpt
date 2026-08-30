--TEST--
assign coalesce 002
--FILE--
<?php
function case1() {
    $context = new ArrayObject();
    $context['session'] = 'world';
    return  $context['session'] ??= 'hello';
}

function case2() {
    $context = new ArrayObject();
    return  $context['session'] ??= 'hello';
}

function case3() {
    $context = [];
    $context['session'] = 'world';
    return  $context['session'] ??= 'hello';
}

function case4() {
    $context = [];
    return  $context['session'] ??= 'hello';
}

function main() {
    error_reporting(E_ERROR);
    var_dump(case1());
    var_dump(case2());
    var_dump(case3());
    var_dump(case4());
}
?>
--EXPECT--
string(5) "world"
string(5) "hello"
string(5) "world"
string(5) "hello"
