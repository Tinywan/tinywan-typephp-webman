--TEST--
args unpack
--FILE--
<?php
function main()
{
    $a = 100;
    $b = 'hello';
    $c = [12.34, true, null];
    var_dump($a, $b, ...$c);
}
?>
--EXPECT--
int(100)
string(5) "hello"
float(12.34)
bool(true)
NULL
