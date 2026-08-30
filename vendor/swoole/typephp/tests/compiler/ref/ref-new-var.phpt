--TEST--
object link operator
--FILE--
<?php
function main()
{
    $name = 'foo_123()';
    $prefix = preg_match('/^([a-z_]+_)/i', $name, $m) ? $m[1] : 'other';
    var_dump($prefix);
}
?>
--EXPECT--
string(4) "foo_"