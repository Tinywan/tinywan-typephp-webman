--TEST--
interface method with `self` return type implemented by class (fluent interface), and namespace block containing comments
--FILE--
<?php

namespace {
    interface TestInterface
    {
        public function get(): self;
    }

    class TestClass implements TestInterface
    {
        public int $value = 0;

        public function get(): self
        {
            return $this;
        }

        public function setValue(int $value): self
        {
            $this->value = $value;
            return $this;
        }
    }

    function main()
    {
        $test = new TestClass;
        // get() returns self, so the result still satisfies the interface
        var_dump($test->get() instanceof TestInterface);
        var_dump($test === $test->get());
        // fluent chaining of self-returning methods
        var_dump($test->get()->setValue(42)->value);
    }
}
?>
--EXPECT--
bool(true)
bool(true)
int(42)
