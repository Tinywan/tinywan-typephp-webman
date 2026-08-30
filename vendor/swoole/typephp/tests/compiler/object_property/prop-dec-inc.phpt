--TEST--
object property inc/dec
--FILE--
<?php
class TestObj {
    public int $a = 999;
    static public int $a1 = 0;
    static public int $b = 100;
}
function main()
{
    $o = new TestObj();
    $o->a++;
    var_dump($o->a);
    $o->a--;
    var_dump($o->a);

    TestObj::$b++;
    var_dump(TestObj::$b);
    TestObj::$b--;
    var_dump(TestObj::$b);
}
?>
--EXPECT--
int(1000)
int(999)
int(101)
int(100)
