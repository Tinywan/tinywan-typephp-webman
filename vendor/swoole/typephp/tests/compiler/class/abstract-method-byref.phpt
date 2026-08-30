--TEST--
abstract method with reference parameter called from base constructor with undefined variable
--FILE--
<?php
abstract class Base
{
    public function __construct()
    {
        // $value 在调用前未定义，按引用传给抽象方法后由实现类赋值
        $this->abc($value);
        var_dump($value);
    }

    abstract public function abc(&$value);
}

class Test extends Base
{
    public function abc(&$value)
    {
        $value = 1;
    }
}

function main()
{
    new Test;
}
?>
--EXPECT--
int(1)
