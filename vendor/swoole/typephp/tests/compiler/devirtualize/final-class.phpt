--TEST--
Devirtualize: final class $this->method() uses native call
--FILE--
<?php

class Base {
    public function name(): string {
        return "base";
    }
}

final class Sealed extends Base {
    public function name(): string {
        return "sealed";
    }

    public function getName(): string {
        return $this->name();
    }
}

function main() {
    $sealed = new Sealed();
    var_dump($sealed->getName());
    var_dump($sealed->name());
}

?>
--EXPECT--
string(6) "sealed"
string(6) "sealed"
