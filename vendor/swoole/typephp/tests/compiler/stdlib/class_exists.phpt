--TEST--
class_exists / interface_exists / trait_exists / enum_exists / defined
--FILE--
<?php
class MyClass {}
interface MyInterface {}
trait MyTrait {}
enum MyEnum { case Foo; }

function main() {
    define("MY_CONST", 42);

    // class_exists
    var_dump(class_exists("MyClass"));
    var_dump(class_exists("MyClass", false));
    var_dump(class_exists("NonexistentClass"));
    var_dump(class_exists("NonexistentClass", false));

    // interface_exists
    var_dump(interface_exists("MyInterface"));
    var_dump(interface_exists("NonexistentInterface"));

    // TypePHP traits are compile-time-only AST templates.
    var_dump(trait_exists("MyTrait"));
    var_dump(trait_exists("NonexistentTrait"));

    // enum_exists
    var_dump(enum_exists("MyEnum"));
    var_dump(enum_exists("NonexistentEnum"));

    // defined
    var_dump(defined("MY_CONST"));
    var_dump(defined("UNDEFINED_CONST"));
}
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(false)
bool(false)
bool(true)
bool(false)
bool(true)
bool(false)
