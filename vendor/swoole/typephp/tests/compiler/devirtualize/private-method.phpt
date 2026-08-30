--TEST--
Devirtualize: private method always uses native call (not virtual)
--FILE--
<?php

class Base {
    private function value(): string {
        return "base-private";
    }

    public function getValue(): string {
        return $this->value();
    }

    private function withArg(int $n): string {
        return "num:" . $n;
    }

    public function testArg(): string {
        return $this->withArg(42);
    }
}

function main() {
    $base = new Base();
    var_dump($base->getValue());
    var_dump($base->testArg());
}

?>
--EXPECT--
string(12) "base-private"
string(6) "num:42"
