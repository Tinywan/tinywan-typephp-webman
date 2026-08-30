<?php
namespace {
    abstract class ByRefMultilevelBase
    {
        abstract public function abc(&$value);

        public function run()
        {
            $this->abc($value);
            var_dump($value);
        }
    }

    abstract class ByRefMultilevelMid extends ByRefMultilevelBase
    {
    }

    class ByRefMultilevelChild extends ByRefMultilevelMid
    {
        public function abc(&$value)
        {
            $value = [1, 2];
        }
    }

    function main()
    {
        (new ByRefMultilevelChild)->run();
    }
}
