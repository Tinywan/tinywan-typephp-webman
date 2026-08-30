--TEST--
Assign by reference to parent static property (parent::$value)
--FILE--
<?php

class Base
{
    public static $value = 1;
}

class Child extends Base
{
    public static function run()
    {
        $ref = &parent::$value;
        var_dump(parent::$value);
        var_dump(Child::$value);
        $ref = 999;
        var_dump(parent::$value);
        var_dump(Child::$value);
        var_dump(Base::$value);
    }
}

function main()
{
    Child::run();
}

?>
--EXPECT--
int(1)
int(1)
int(999)
int(999)
int(999)
