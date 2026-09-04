--TEST--
class_exists / interface_exists / trait_exists / enum_exists / defined
--FILE--
<?php
class MyClass {}
interface MyInterface {}
trait MyTrait {}
enum MyEnum { case Foo; }

function autoloadFlag(string $label): bool
{
    echo "autoload-$label\n";
    return false;
}

function throwingAutoloadFlag(): bool
{
    echo "autoload-throw\n";
    throw new RuntimeException('autoload-argument');
}

function main() {
    define("MY_CONST", 42);

    // class_exists
    var_dump(class_exists("MyClass"));
    var_dump(class_exists("MyClass", false));
    var_dump(class_exists("NonexistentClass"));
    var_dump(class_exists("NonexistentClass", false));

    // A trait is not a class. The answer must not depend on whether the name
    // is a literal the compiler can resolve at compile time.
    var_dump(class_exists("MyTrait"));
    $traitName = "MyTrait";
    var_dump(class_exists($traitName));

    // An explicit autoload expression must be evaluated exactly once even
    // when the literal name makes the final answer statically known.
    var_dump(class_exists("MyClass", autoloadFlag('class')));
    var_dump(class_exists("MyTrait", autoloadFlag('trait')));
    try {
        class_exists("MyClass", throwingAutoloadFlag());
        echo "exception-not-thrown\n";
    } catch (RuntimeException $e) {
        echo "caught=", $e->getMessage(), "\n";
    }

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
bool(false)
bool(false)
autoload-class
bool(true)
autoload-trait
bool(false)
autoload-throw
caught=autoload-argument
bool(true)
bool(false)
bool(false)
bool(false)
bool(true)
bool(false)
bool(true)
bool(false)
