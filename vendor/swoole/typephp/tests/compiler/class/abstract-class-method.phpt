--TEST--
abstract class and abstract method
--FILE--
<?php
namespace {
    use App1\Dog;
    use App1\Animal;

    function foo(Animal $animal) {
        $rs = $animal->speak();
        var_dump($rs);
    }

    function main() {
        include __DIR__ . "/abstract-class.inc";
        $dog = new Dog("Buddy");
        foo($dog);
        echo "done\n";
    }
}
?>
--EXPECT--
string(4) "woof"
done
