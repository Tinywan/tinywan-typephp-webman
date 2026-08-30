--TEST--
Anonymous Classes - Runtime class definition
--FILE--
<?php

namespace TestApp {
    class Greeter {
        protected string $greeting;

        public function __construct(string $greeting) {
            $this->greeting = $greeting;
        }

        public function greet() {
            return $this->greeting;
        }
    }
}


namespace {
use TestApp\Greeter;
function main() {
    $class = new class("Hello") extends Greeter {
        public function greet() {
            return "{$this->greeting} from anonymous class";
        }
    };
    echo $class->greet(), "\n";
}
}

?>
--EXPECT--
Hello from anonymous class
