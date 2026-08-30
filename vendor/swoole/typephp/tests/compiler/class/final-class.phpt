--TEST--
final class and final method
--FILE--
<?php

class Base {
    public function overridable(): string {
        return "base";
    }

    final public function locked(): string {
        return "cannot override";
    }
}

class Derived extends Base {
    public function overridable(): string {
        return "derived";
    }
}

final class Sealed {
    public function value(): int {
        return 42;
    }
}

function main() {
    $base = new Base();
    var_dump($base->overridable());
    var_dump($base->locked());

    $derived = new Derived();
    var_dump($derived->overridable());
    var_dump($derived->locked());

    $sealed = new Sealed();
    var_dump($sealed->value());

    echo "done\n";
}

?>
--EXPECT--
string(4) "base"
string(15) "cannot override"
string(7) "derived"
string(15) "cannot override"
int(42)
done
