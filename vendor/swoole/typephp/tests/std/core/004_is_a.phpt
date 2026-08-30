--TEST--
is_a() / is_subclass_of() functions
--FILE--
<?php

class Base {}
class Child extends Base {}
interface IFace {}
class Impl implements IFace {}

function main() {
    $child = new Child();
    $impl = new Impl();

    echo "is_a:\n";
    echo is_a($child, "Child") ? "ok-obj-class\n" : "fail\n";
    echo is_a($child, "Base") ? "ok-obj-parent\n" : "fail\n";
    echo is_a($child, "NoSuchClass") ? "fail\n" : "ok-obj-none\n";

    echo "is_subclass_of:\n";
    echo is_subclass_of($child, "Base") ? "ok-obj-parent\n" : "fail\n";
    echo is_subclass_of($child, "Child") ? "fail\n" : "ok-obj-same\n";
    echo is_subclass_of(Child::class, "Base") ? "ok-cls-parent\n" : "fail\n";
    echo is_subclass_of(Child::class, "Child") ? "fail\n" : "ok-cls-same\n";
    echo is_subclass_of($child, "NoSuchClass") ? "fail\n" : "ok-none\n";

    echo "done\n";
}
?>
--EXPECT--
is_a:
ok-obj-class
ok-obj-parent
ok-obj-none
is_subclass_of:
ok-obj-parent
ok-obj-same
ok-cls-parent
ok-cls-same
ok-none
done
