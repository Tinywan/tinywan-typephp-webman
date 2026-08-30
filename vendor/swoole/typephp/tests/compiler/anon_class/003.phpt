--TEST--
Anonymous Classes - magic methods keep undeclared return semantics
--FILE--
<?php

function main() {
    $obj = new class {
        public int $value = 0;

        public function __set($name, $value) {
            $this->value = $value;
            return 1;
        }
    };

    $obj->dynamic = 2;
    var_dump($obj->value);
}
?>
--EXPECT--
int(2)
