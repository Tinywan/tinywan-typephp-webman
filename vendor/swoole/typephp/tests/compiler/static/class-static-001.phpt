--TEST--
class static
--FILE--
<?php
namespace Test {
    #[AllowDynamicProperties]
    class Worker
    {
        public const FOO = 'foo';
        protected static string $hello;
        public function __construct()
        {
            self::$hello = 'hello';
            var_dump(self::$hello);
            static::$hello = 'world';

            var_dump(static::$hello);
            var_dump(static::FOO);
        }
    }
}
namespace  {
    function main()
    {
        $obj = new \Test\Worker();
    }
}
?>
--EXPECT--
string(5) "hello"
string(5) "world"
string(3) "foo"
