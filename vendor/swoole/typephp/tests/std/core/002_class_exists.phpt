--TEST--
class_exists() / interface_exists() / trait_exists() / enum_exists() functions
--FILE--
<?php

class MyClass {}
interface MyInterface {}
trait MyTrait {}
enum MyEnum { case A; }

function main() {
    echo "class_exists:\n";
    echo class_exists("MyClass") ? "ok-true\n" : "fail\n";
    echo class_exists("NonExistentClass") ? "fail\n" : "ok-false\n";

    echo "interface_exists:\n";
    echo interface_exists("MyInterface") ? "ok-true\n" : "fail\n";
    echo interface_exists("NonExistentInterface") ? "fail\n" : "ok-false\n";

    echo "trait_exists:\n";
    echo trait_exists("MyTrait") ? "fail\n" : "ok-false\n";
    echo trait_exists("NonExistentTrait") ? "fail\n" : "ok-false\n";

    echo "enum_exists:\n";
    echo enum_exists("MyEnum") ? "ok-true\n" : "fail\n";
    echo enum_exists("NonExistentEnum") ? "fail\n" : "ok-false\n";

    echo "done\n";
}
?>
--EXPECT--
class_exists:
ok-true
ok-false
interface_exists:
ok-true
ok-false
trait_exists:
ok-false
ok-false
enum_exists:
ok-true
ok-false
done
