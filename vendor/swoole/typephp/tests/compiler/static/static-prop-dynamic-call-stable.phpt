--TEST--
Static property local slots survive dynamic PHP calls
--FILE--
<?php
use native_types;

class StaticDynamicCallStable {
    public static int $i = 1;
    public static string $s = "seed";
}

function main(): void {
    StaticDynamicCallStable::$i = 10;
    StaticDynamicCallStable::$s = "before";

    eval('function mutate_static(): void { StaticDynamicCallStable::$i = 99; StaticDynamicCallStable::$s = "changed"; } mutate_static();');

    StaticDynamicCallStable::$i += 1;
    StaticDynamicCallStable::$s .= "!";

    var_dump(StaticDynamicCallStable::$i);
    var_dump(StaticDynamicCallStable::$s);
}
?>
--EXPECT--
int(100)
string(8) "changed!"
