--TEST--
place-holder
--FILE--
<?php
class TestPlaceHolder {
    static function foo()
    {
        var_dump(__FUNCTION__);
        $fn4 = (self::bar(...));
        $fn4();
    }

    static function bar()
    {
        var_dump(__METHOD__);
    }

    function test() {
         var_dump(__METHOD__);
    }
}

function main()
{
    $obj = new TestPlaceHolder;
    $fn1 = $obj->test(...);
    $fn1();

    $fn2 = var_dump(...);
    $fn2(__LINE__);

    $fn3 = TestPlaceHolder::foo(...);
    $fn3();

    TestPlaceHolder::foo();
}
?>
--EXPECT--
string(21) "TestPlaceHolder::test"
int(27)
string(3) "foo"
string(20) "TestPlaceHolder::bar"
string(3) "foo"
string(20) "TestPlaceHolder::bar"
