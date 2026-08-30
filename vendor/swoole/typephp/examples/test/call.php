<?php
namespace app\test {
    function fn1()
    {
        echo "fn1\n";
        fn2();
    }

    function fn2()
    {
        echo __FUNCTION__ . "\n";
    }
}
