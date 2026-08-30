--TEST--
instanceof operator
--FILE--
<?php

class Base {}
class Derived extends Base {}
interface MyInterface {}
class Implementor implements MyInterface {}

function main() {
    $base = new Base();
    $derived = new Derived();
    $implem = new Implementor();

    var_dump($base instanceof Base);
    var_dump($derived instanceof Base);
    var_dump($base instanceof Derived);
    var_dump($implem instanceof MyInterface);
    var_dump($base instanceof MyInterface);
    var_dump(null instanceof Base);

    echo "done\n";
}

?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(true)
bool(false)
bool(false)
done
