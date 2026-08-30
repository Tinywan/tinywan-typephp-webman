<?php


class Test2 extends Test
{
    function fun(string $name)
    {
        var_dump(__CLASS__);
        var_dump($name);
    }
}


class Test
{
    private $a;
    private string $b;
    private int $c = 0;

    public string $d = 'hello';
    public array $e = [1, 2, 3];

    public const int T_E = 1;
    protected const T_S = 'hello';
    private const T_A = [1, 2, 3];

    public function __construct()
    {
        $this->a = 1;
        $this->b = 'hello';
        $this->c = 0;
    }

    public function test(int $a, int $b)
    {
        return $this->a + $this->b + $a + $b;
    }
}

const TEST_CONST = 1.001;

function my_sum(int $a, int $b)
{
    return $a + $b;
}

