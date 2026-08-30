--TEST--
place-holder
--FILE--
<?php
class TestPlaceHolder {
    static function foo()
    {
        $fn4 = (static::bar(...));
        $fn4();
    }

    static function bar()
    {
        var_dump(__METHOD__);
    }
}

class TestPlaceHolder2 extends TestPlaceHolder {
    static function bar()
    {
        echo "baz\n";
    }
}

function main()
{
    TestPlaceHolder2::foo();
}
?>
--EXPECT--
baz