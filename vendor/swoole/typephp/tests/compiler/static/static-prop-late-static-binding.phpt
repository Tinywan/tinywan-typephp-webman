--TEST--
Static native properties use late static binding for static::$prop
--FILE--
<?php
use native_types;

class StaticPropBase {
    public static int $v = 1;

    public static function get(): int {
        return static::$v;
    }

    public static function set(int $v): void {
        static::$v = $v;
    }
}

class StaticPropChild extends StaticPropBase {
    public static int $v = 2;
}

function main(): void {
    var_dump(StaticPropBase::get());
    var_dump(StaticPropChild::get());

    StaticPropChild::set(9);

    var_dump(StaticPropBase::$v);
    var_dump(StaticPropChild::$v);
    var_dump(StaticPropChild::get());
}
?>
--EXPECT--
int(1)
int(2)
int(1)
int(9)
int(9)
