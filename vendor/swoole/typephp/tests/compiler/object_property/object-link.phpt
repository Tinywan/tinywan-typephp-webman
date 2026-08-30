--TEST--
object link operator
--FILE--
<?php
function main()
{
    $o = new stdClass();
    $o->prop = ['dim2' => ['dim3' => 'value']];
    var_dump($o->prop['dim2']['dim3']);
    $o->prop['dim2']['dim3'] = 'hello';
    var_dump($o->prop['dim2']['dim3']);
}
?>
--EXPECT--
string(5) "value"
string(5) "hello"
