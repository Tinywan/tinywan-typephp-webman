--TEST--
class static 004
--FILE--
<?php
namespace Test {
    class Worker1
    {
        static string $var = 'hello';

        static function foo()
        {
            var_dump(self::$var);
            var_dump(static::$var);
        }

        static function bar()
        {
            static::$var = 'world';
        }
    }

    class Worker2 extends Worker1
    {
        static string $var = 'swoole';
    }
}

namespace  {
    use Test\Worker2;
    function main()
    {
        Worker2::foo();
        Worker2::bar();
        Worker2::foo();
    }
}
?>
--EXPECT--
string(5) "hello"
string(6) "swoole"
string(5) "hello"
string(5) "world"

