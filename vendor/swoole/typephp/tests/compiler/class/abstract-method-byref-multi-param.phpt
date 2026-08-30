--TEST--
abstract method with two reference parameters, one defined and one undefined
--FILE--
<?php
namespace {
    abstract class Base
    {
        abstract public function abc(&$a, &$b);

        public function run()
        {
            $x = 5;
            // $x 已定义；$y 未定义，按引用传参后由实现类赋值
            $this->abc($x, $y);
            var_dump($x, $y);
        }
    }

    class Test extends Base
    {
        public function abc(&$a, &$b)
        {
            $a *= 2;
            $b = 'done';
        }
    }

    function main()
    {
        (new Test)->run();
    }
}
?>
--EXPECT--
int(10)
string(4) "done"
