--TEST--
class static
--FILE--
<?php
namespace Test {
    class Worker1
    {
        static function hello()
        {
            return "hello";
        }

        static function foo()
        {
            var_dump(self::hello());
            var_dump(static::hello());
        }
    }

    class Worker2 extends Worker1
    {
        static function hello()
        {
            return "world";
        }
    }
}

namespace  {
    use Test\Worker2;
    function main()
    {
        Worker2::foo();
    }
}
?>
--EXPECT--
string(5) "hello"
string(5) "world"
