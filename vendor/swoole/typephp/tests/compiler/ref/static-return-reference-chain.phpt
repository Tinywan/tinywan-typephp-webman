--TEST--
Static method returning by reference can forward another by-reference static call
--FILE--
<?php
class Counter
{
    private static $value = 0;

    public static function &next()
    {
        self::$value++;
        return self::$value;
    }

    public static function &current()
    {
        return self::next();
    }
}

function main()
{
    var_dump(Counter::current());
    $ref = &Counter::current();
    $ref = 10;
    var_dump(Counter::next());
}

// main();
?>
--EXPECT--
int(1)
int(11)
