--TEST--
object property ref
--FILE--
<?php
class TestRef {
    public int $a = 999;
}
function main()
{
    $o = new TestRef();
    $ref = &$o->a;
    $ref = 2026;
    var_dump($o->a);
}
?>
--EXPECT--
int(2026)