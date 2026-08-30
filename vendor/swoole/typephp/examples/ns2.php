<?php


namespace Foo1\Bar {
    use \StdClass;
    function foo(int $a, int $b): int
    {
        return $a + $b;
    }

    function foo2(): stdClass
    {
        return new stdClass();
    }
}

namespace Foo2\Bar {
    function foo2(int $a, int $b): int
    {
        return $a + $b;
    }
}