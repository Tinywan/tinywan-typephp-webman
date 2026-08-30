--TEST--
Concat flattening to variadic php::concat(ArgList)
--FILE--
<?php
function main(): void {
    $a = 'hello';
    $b = ' ';
    $c = 'world';
    $d = '!';
    var_dump($a . $b . $c . $d);

    $x = 'pre_';
    $y = 42;
    var_dump($x . $y . '_suffix');

    var_dump('a' . 'b');
}
?>
--EXPECT--
string(12) "hello world!"
string(13) "pre_42_suffix"
string(2) "ab"
