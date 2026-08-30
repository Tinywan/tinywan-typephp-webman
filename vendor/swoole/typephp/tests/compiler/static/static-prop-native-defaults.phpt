--TEST--
Static properties with native_types keep defaults and local slots
--FILE--
<?php
use native_types;

class StaticNativeDefaults {
    public static int $i = 42;
    public static float $f = 3.5;
    public static bool $b = true;
    public static string $s = "seed";
    public static array $a = [1, 2];
}

function main(): void {
    var_dump(StaticNativeDefaults::$i);
    var_dump(StaticNativeDefaults::$f);
    var_dump(StaticNativeDefaults::$b);
    var_dump(StaticNativeDefaults::$s);
    var_dump(StaticNativeDefaults::$a);

    StaticNativeDefaults::$i = 100;
    StaticNativeDefaults::$f = 2.25;
    StaticNativeDefaults::$b = false;
    StaticNativeDefaults::$s = "changed";
    StaticNativeDefaults::$a = [9];

    var_dump(StaticNativeDefaults::$i);
    var_dump(StaticNativeDefaults::$f);
    var_dump(StaticNativeDefaults::$b);
    var_dump(StaticNativeDefaults::$s);
    var_dump(StaticNativeDefaults::$a);
}
?>
--EXPECT--
int(42)
float(3.5)
bool(true)
string(4) "seed"
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
int(100)
float(2.25)
bool(false)
string(7) "changed"
array(1) {
  [0]=>
  int(9)
}
