--TEST--
ZE2 dereferencing of objects from methods
--SKIPIF--
<?php die('skip'); ?>
--FILE--
<?php

class Name {
    function __construct(public string $name) {}

    function display() {
        echo $this->name . "\n";
    }
}

class Person {
    private $name;

    function __construct($_name, $_address) {
        $this->name = new Name($_name);
    }

    function getName() {
        return $this->name;
    }
}

function main() {
    $person = new Person("John", "New York");
    $person->getName()->display();
}

?>
--EXPECT--
John
