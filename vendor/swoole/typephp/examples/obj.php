<?php

class Test
{
    public int $a;
    public int $b;

    public function __construct(int $a, int $b)
    {
        $this->a = $a;
        $this->b = $b;
    }

    public function testArg(int $a, int $b = 3, string $s = 'hello'): int
    {
        return $a + $b + strlen($s);
    }

    public function test()
    {
        return $this->a + $this->b;
    }
}

function main()
{
    $obj = new Test(1, 2);

    $arr = array();
    $arr['obj'] = $obj;
    var_dump($obj->test());

    $obj2 = objval($arr['obj'], 'Test');
    var_dump($obj2);
    var_dump($obj2->test());
}