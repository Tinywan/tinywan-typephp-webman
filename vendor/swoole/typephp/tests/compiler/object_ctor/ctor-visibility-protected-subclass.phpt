--TEST--
Constructor visibility - protected constructor accessible from subclass
--FILE--
<?php

class Base
{
    protected function __construct(){}
}

class Sub extends Base
{
    public static function make(): Base
    {
        return new Base();
    }
}

function main()
{
    $obj = Sub::make();
    var_dump($obj instanceof Base);
}
?>
--EXPECT--
bool(true)
