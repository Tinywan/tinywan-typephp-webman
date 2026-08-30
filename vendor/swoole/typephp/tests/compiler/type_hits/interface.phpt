--TEST--
type hits
--FILE--
<?php
interface Quackable {
    public function quack(): void;
}

class Duck implements Quackable {
    public function quack(): void {
        echo "Quack!\n";
    }
}

class Person implements Quackable {
    public function quack(): void {
        echo "I'm imitating a duck!\n";
    }
}

function makeItQuack(Quackable $thing): void {
    $thing->quack();
}

function main()
{
    makeItQuack(new Duck());
    makeItQuack(new Person());
}
?>
--EXPECT--
Quack!
I'm imitating a duck!