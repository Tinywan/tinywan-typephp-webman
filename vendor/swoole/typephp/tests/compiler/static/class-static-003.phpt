--TEST--
class static 003
--FILE--
<?php
namespace Test {
    class Worker1
    {
        static function hello()
        {
            return new static();
        }

        static function foo()
        {
           return self::hello();
        }
    }

    class Worker2 extends Worker1
    {
    }
}

namespace  {
    use Test\Worker2;
    function main()
    {
        $o = Worker2::foo();
        var_dump(get_class($o));
    }
}
?>
--EXPECT--
string(12) "Test\Worker2"
