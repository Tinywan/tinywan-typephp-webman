<?php

namespace App {
    class Base
    {
        function foo2()
        {
            var_dump(__METHOD__);
        }
    }

    use \override as myoverride;

    class Child extends Base
    {
        #[myoverride]
        function foo()
        {
            var_dump(__LINE__ . '()' . __METHOD__);
        }
    }
}

namespace {
    function main()
    {
        $c = new App\Child();
        $c->foo();
    }
}