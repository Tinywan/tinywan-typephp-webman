--TEST--
object link operator 003
--FILE--
<?php
function main()
{
    $o = new stdClass();
    $o->prop = new stdClass();
    $o->prop->value = "value";
    $o->prop->value .= " 2025";
    var_dump($o->prop->value);
}
?>
--EXPECT--
string(10) "value 2025"
