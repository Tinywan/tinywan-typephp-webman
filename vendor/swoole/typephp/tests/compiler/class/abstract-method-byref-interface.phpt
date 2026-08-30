--TEST--
interface method declared with reference parameter, implemented by a class
--FILE--
<?php
namespace {
    interface IByRef
    {
        public function abc(&$value);
    }

    class Test implements IByRef
    {
        public function abc(&$value)
        {
            $value = 'x';
        }
    }

    function main()
    {
        $t = new Test;
        // $v 未定义，按引用传给接口的按引用方法后由实现类赋值
        $t->abc($v);
        var_dump($v);
    }
}
?>
--EXPECT--
string(1) "x"
