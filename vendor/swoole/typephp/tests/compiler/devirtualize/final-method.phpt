--TEST--
Devirtualize: final method uses native call
--FILE--
<?php

class Animal {
    final public function type(): string {
        return "animal";
    }

    public function getType(): string {
        return $this->type();
    }
}

class Dog extends Animal {
    public function name(): string {
        return "dog";
    }
}

function main() {
    $animal = new Animal();
    var_dump($animal->getType());

    $dog = new Dog();
    var_dump($dog->getType());
    var_dump($dog->name());
}

?>
--EXPECT--
string(6) "animal"
string(6) "animal"
string(3) "dog"
