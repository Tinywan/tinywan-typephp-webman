<?php

class B extends A
{
    public function __construct()
    {
        echo "B::__construct()\n";
        parent::__construct();
    }


    public function foo()
    {
        $this->bar2();
    }

    function bar2()
    {
        var_dump(__METHOD__);
    }
}