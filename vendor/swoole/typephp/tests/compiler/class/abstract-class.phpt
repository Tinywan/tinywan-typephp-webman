--TEST--
abstract class and abstract method
--FILE--
<?php

abstract class Animal {
    protected string $name;

    public function __construct(string $name) {
        $this->name = $name;
    }

    abstract public function speak(): string;

    public function getName(): string {
        return $this->name;
    }

    public function describe(): string {
        return $this->getName() . " says " . $this->speak();
    }
}

class Dog extends Animal {
    public function speak(): string {
        return "woof";
    }
}

class Cat extends Animal {
    public function speak(): string {
        return "meow";
    }
}

function main() {
    $dog = new Dog("Buddy");
    $cat = new Cat("Kitty");

    var_dump($dog->getName());
    var_dump($dog->speak());
    var_dump($dog->describe());

    var_dump($cat->getName());
    var_dump($cat->speak());

    echo "done\n";
}

?>
--EXPECT--
string(5) "Buddy"
string(4) "woof"
string(15) "Buddy says woof"
string(5) "Kitty"
string(4) "meow"
done
