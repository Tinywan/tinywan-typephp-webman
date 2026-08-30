--TEST--
abstract method with reference parameter across multiple levels of inheritance
--FILE--
<?php
namespace {
    abstract class Base
    {
        abstract public function abc(&$value);

        public function run()
        {
            // $value 在调用前未定义，按引用传给抽象方法后由最终实现类赋值
            $this->abc($value);
            var_dump($value);
        }
    }

    // 中间类继续继承抽象方法，不实现
    abstract class Mid extends Base
    {
    }

    class Test extends Mid
    {
        public function abc(&$value)
        {
            $value = [1, 2];
        }
    }

    function main()
    {
        (new Test)->run();
    }
}
?>
--EXPECT--
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
