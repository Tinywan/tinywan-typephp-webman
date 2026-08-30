--TEST--
abstract method with reference parameter, passing an already-defined variable
--FILE--
<?php
namespace {
    abstract class Base
    {
        abstract public function abc(&$value);

        public function run()
        {
            $v = 0;
            $this->abc($v);
            var_dump($v);
        }
    }

    class Test extends Base
    {
        public function abc(&$value)
        {
            $value = 42;
        }
    }

    function main()
    {
        (new Test)->run();
    }
}
?>
--EXPECT--
int(42)
