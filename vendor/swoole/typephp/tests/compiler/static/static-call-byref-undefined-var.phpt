--TEST--
Static method call passing an undefined variable by reference (late static binding)
--FILE--
<?php

class Test
{
    public static function getName(?string &$name)
    {
        $name = 'test';
    }

    public static function run()
    {
        // $fileName is undefined; passing it by reference must auto-create it
        // instead of raising an "Undefined variable" fatal error.
        static::getName($fileName);
        var_dump($fileName);
        Test2::dump($fileName);
    }
}

class Test2
{
    public static function dump(?string $value)
    {
        var_dump($value);
    }
}

function main()
{
    Test::run();
}
?>
--EXPECT--
string(4) "test"
string(4) "test"
