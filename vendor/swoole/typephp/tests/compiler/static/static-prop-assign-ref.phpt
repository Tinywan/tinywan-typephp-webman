--TEST--
Assign by reference to static property (self / static / class name)
--FILE--
<?php

class Test
{
    private static $value = 123;

    public static function abc(): void
    {
        $a = &self::$value;
        var_dump($a);
        $a = 456;
        var_dump(self::$value);

        $b = &static::$value;
        var_dump($b);
        $b = 789;
        var_dump(static::$value);

        $c = &Test::$value;
        var_dump($c);
        $c = 1000;
        var_dump(Test::$value);
    }
}

function main(): void
{
    Test::abc();
}
?>
--EXPECT--
int(123)
int(456)
int(456)
int(789)
int(789)
int(1000)
