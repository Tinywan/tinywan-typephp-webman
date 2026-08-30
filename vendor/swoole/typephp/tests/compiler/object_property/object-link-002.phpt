--TEST--
object link operator 002
--FILE--
<?php
function main()
{
    $o = new stdClass();
    $o->prop = new stdClass();
    $o->prop->value = "value";
    var_dump($o->prop->value);
}
?>
--EXPECT--
string(5) "value"
