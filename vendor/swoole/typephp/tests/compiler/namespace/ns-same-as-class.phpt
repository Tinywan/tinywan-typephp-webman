--TEST--
use class A\B (without alias) inside namespace A\B, call B::$v
--FILE--
<?php
namespace A {
    class B
    {
        public static $v = 123;
    }
}

namespace A\B {

    use A\B;

    function testProp(): int {
        return B::$v;
    }

    class C extends B {
        public static function testSelf(): int
        {
            return self::$v;
        }

        public static function testStatic(): int
        {
            return static::$v;
        }

        public static function testParent(): int
        {
            return parent::$v;
        }
    }
}

namespace {
    function main() {
        var_dump(\A\B\testProp());
        var_dump(\A\B\C::testSelf());
        var_dump(\A\B\C::testStatic());
        var_dump(\A\B\C::testParent());
    }
}

?>
--EXPECT--
int(123)
int(123)
int(123)
int(123)
