--TEST--
class method override
--FILE--
<?php
namespace Test {
    class Worker1
    {
        protected string $id = 'foo';
        function hello()
        {
            var_dump($this->id);
            return "hello";
        }

        function foo()
        {
            var_dump($this->hello());
            var_dump(self::hello());
        }
    }

    class Worker2 extends Worker1
    {
        function hello()
        {
            return "world";
        }
    }
}

namespace  {
    use Test\Worker2;
    function main()
    {
        $obj = new Worker2();
        $obj->hello();
        $obj->foo();
    }
}
?>
--EXPECT--
string(5) "world"
string(3) "foo"
string(5) "hello"
