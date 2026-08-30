--TEST--
parent::method() call
--FILE--
<?php
class Base {
    public function greet(): string {
        return "Hello from Base";
    }

    public function value(): int {
        return 10;
    }

    public static function staticValue(): int {
        return 100;
    }
}

class Child extends Base {
    public function greet(): string {
        return parent::greet() . " and Child";
    }

    public function value(): int {
        return parent::value() + 5;
    }

    public function getParentGreet(): string {
        return parent::greet();
    }

    public static function staticValue(): int {
        return parent::staticValue() + 50;
    }
}

function main(): void {
    $child = new Child();
    echo $child->greet() . "\n";
    echo $child->value() . "\n";
    echo $child->getParentGreet() . "\n";
    echo Child::staticValue() . "\n";
}
?>
--EXPECT--
Hello from Base and Child
15
Hello from Base
150
