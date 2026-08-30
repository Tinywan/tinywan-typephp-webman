<?php
namespace Foo1\Bar;

class Tex {
    const FOO = 1;
    public $a = 1;

    public function foo(int $a, int $b): int
    {
        $o = new \stdClass($a, $b);
        $c = clone $o;
        return $a + $b;
    }
}
