--TEST--
Cross-namespace interface implementation parameter compatibility is declaration-order independent
--FILE--
<?php
namespace A {
    use B\I0;
    use B\I1;
    use B\I2;

    abstract class Test implements I2
    {
        public function test(I1 $a): bool
        {
            return true;
        }

        public function testParent(I0 $a): bool
        {
            return true;
        }
    }
}

namespace B {
    interface I0
    {
    }

    interface I1 extends I0
    {
    }

    interface I2
    {
        public function test(I1 $a): bool;

        public function testParent(I1 $a): bool;
    }

    class Impl1 implements I1
    {
    }
}

namespace {
    class Concrete extends \A\Test
    {
    }

    function main()
    {
        $obj = new Concrete();
        var_dump($obj->test(new \B\Impl1()));
        var_dump($obj->testParent(new \B\Impl1()));
        echo "done\n";
    }
}
?>
--EXPECT--
bool(true)
bool(true)
done
