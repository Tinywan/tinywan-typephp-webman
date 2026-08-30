--TEST--
trait full class name
--FILE--
<?php
trait TraitA {
    public function sayHello() {
        var_dump(__TRAIT__);
        var_dump(__CLASS__);
    }
}

class MyHelloWorld
{
    use TraitA;
    public function sayHelloWorld() {
        $this->sayHello();
    }
}

function main()
{
    $obj = new MyHelloWorld();
    $obj->sayHelloWorld();
}
?>
--EXPECT--
string(6) "TraitA"
string(12) "MyHelloWorld"