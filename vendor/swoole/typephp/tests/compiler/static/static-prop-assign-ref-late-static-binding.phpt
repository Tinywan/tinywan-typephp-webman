--TEST--
Assign by reference to late-static-bound property resolves to called class
--FILE--
<?php
use native_types;

class Base {
    protected static int $value = 1;

    public static function bump(): void {
        $ref = &static::$value;
        $ref = 99;
    }

    public static function show(): void {
        var_dump(static::$value);
    }
}

class Child extends Base {
    protected static int $value = 2;
}

function main(): void {
    Base::show();
    Child::bump();
    Child::show();
    Base::show();
}
?>
--EXPECT--
int(1)
int(99)
int(1)
