<?php

trait TraitA {
    public function sayHello() {
        echo 'Hello';
        var_dump(__TRAIT__);
        var_dump(__CLASS__);
    }
}

trait TraitB {
    public function sayWorld() {
        echo 'World';
    }
}

class MyHelloWorld
{
    use TraitA, TraitB; // A class can use multiple traits

    public function sayHelloWorld() {
        $this->sayHello();
        echo ' ';
        $this->sayWorld();
        echo "!\n";
    }
}

function main()
{
    $obj = new MyHelloWorld();
    $obj->sayHelloWorld();
}
