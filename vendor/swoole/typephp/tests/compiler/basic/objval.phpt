--TEST--
objval
--FILE--
<?php

class TestObjval
{
    public int $a;
    public int $b;

    public function __construct(int $a, int $b)
    {
        $this->a = $a;
        $this->b = $b;
    }

    public function test()
    {
        return $this->a + $this->b;
    }
}

function main()
{
    $obj = new TestObjval(1, 2);
    $arr = array();
    $arr['obj'] = $obj;
    $obj2 = $arr['obj']->toObject('TestObjval');
    var_dump($obj2->test());

    $obj3 = $obj->toObject(TestObjval::class);
    var_dump($obj3->test());
}
?>
--EXPECT--
int(3)
int(3)