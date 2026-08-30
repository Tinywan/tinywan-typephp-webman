<?php
namespace {
    interface IByRef
    {
        public function abc(&$value);
    }

    class ByRefInterfaceImpl implements IByRef
    {
        public function abc(&$value)
        {
            $value = 'x';
        }
    }

    function main()
    {
        $t = new ByRefInterfaceImpl;
        $t->abc($v);
        var_dump($v);
    }
}
