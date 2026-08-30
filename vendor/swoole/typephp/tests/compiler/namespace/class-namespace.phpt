--TEST--
class namespace
--FILE--
<?php
namespace Test {
    class Session {
        public static function init()
        {
            var_dump(__METHOD__);
        }
    }

    function foo()
    {
        var_dump(__FUNCTION__);
    }
}
namespace  {
    use Test\Session;
    function main()
    {
        Session::init();
        Test\foo();
    }
}
?>
--EXPECT--
string(18) "Test\Session::init"
string(8) "Test\foo"