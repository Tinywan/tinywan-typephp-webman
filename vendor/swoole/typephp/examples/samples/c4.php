<?php
class Base {
    function bar()
    {
        var_dump(__METHOD__);
    }

    public function run()
    {
        $this->bar();
    }
}

class Child extends Base
{
    function bar()
    {
        var_dump(__METHOD__);
    }
}

function my_uname_func()
{
    var_dump(php_uname());
}

function main()
{
    $obj1 = new Child();
    $obj1->run();

    $obj2 = new Base();
    $obj2->run();
}
