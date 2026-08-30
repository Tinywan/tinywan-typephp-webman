<?php
namespace A {
    abstract class AbstractByRefBase
    {
        public function __construct()
        {
            $this->abc($value);
            var_dump($value);
        }

        abstract public function abc(&$value);
    }
}

namespace B {
    use A\AbstractByRefBase;

    class AbstractByRefChild extends AbstractByRefBase
    {
        public function abc(&$value)
        {
            $value = [1];
        }
    }
}

namespace {
    use B\AbstractByRefChild;

    function main()
    {
        new AbstractByRefChild;
    }
}
