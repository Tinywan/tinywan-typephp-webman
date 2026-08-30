--TEST--
Devirtualize: overridden method stays dynamic (no false devirtualization)
--FILE--
<?php

class Parent_ {
    public function greet(): string {
        return "parent";
    }

    public function sayHello(): string {
        return $this->greet();
    }
}

class Child_ extends Parent_ {
    public function greet(): string {
        return "child";
    }
}

function main() {
    $parent = new Parent_();
    var_dump($parent->sayHello());

    $child = new Child_();
    var_dump($child->sayHello());
    var_dump($child->greet());
}

?>
--EXPECT--
string(6) "parent"
string(5) "child"
string(5) "child"
