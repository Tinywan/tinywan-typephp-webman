--TEST--
dynamic static property name read and write
--FILE--
<?php

class DynamicStaticPropertyTarget
{
    public static int $count = 1;
}

function choose_static_property(): string
{
    echo "prop\n";
    return 'count';
}

function main(): void
{
    $prop = choose_static_property();
    var_dump(DynamicStaticPropertyTarget::$$prop);

    DynamicStaticPropertyTarget::$$prop = 5;
    var_dump(DynamicStaticPropertyTarget::$count);
}
?>
--EXPECT--
prop
int(1)
int(5)
