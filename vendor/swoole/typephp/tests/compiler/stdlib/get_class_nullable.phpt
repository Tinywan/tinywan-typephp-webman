--TEST--
get_class on nullable and polymorphic objects behaves identically to PHP
--FILE--
<?php

class Animal
{
}

class Dog extends Animal
{
}

class Cat extends Animal
{
}

function makeAnimal(): ?Animal
{
    return new Dog();
}

function makeNullAnimal(): ?Animal
{
    return null;
}

function testPolymorphic(): void
{
    $animal = makeAnimal();
    var_dump(get_class($animal));
}

function testExact(): void
{
    $cat = new Cat();
    var_dump(get_class($cat));
}

function testNullable(): void
{
    $animal = makeNullAnimal();
    try {
        var_dump(get_class($animal));
    } catch (TypeError $e) {
        echo "Caught nullable TypeError: " . $e->getMessage() . "\n";
    }

    try {
        var_dump(get_class(null));
    } catch (TypeError $e) {
        echo "Caught literal TypeError: " . $e->getMessage() . "\n";
    }
}

function main(): void
{
    testPolymorphic();
    testExact();
    testNullable();
}
?>
--EXPECT--
string(3) "Dog"
string(3) "Cat"
Caught nullable TypeError: get_class(): Argument #1 ($object) must be of type object, null given
Caught literal TypeError: get_class(): Argument #1 ($object) must be of type object, null given
