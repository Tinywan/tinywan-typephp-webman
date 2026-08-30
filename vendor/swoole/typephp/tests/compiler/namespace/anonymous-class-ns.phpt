--TEST--
Anonymous class inside namespace
--FILE--
<?php
namespace App\Factory {
    interface Greeter {
        public function greet(string $name): string;
    }

    function createGreeter(string $prefix): Greeter {
        return new class($prefix) implements Greeter {
            private string $prefix;
            public function __construct(string $prefix) {
                $this->prefix = $prefix;
            }
            public function greet(string $name): string {
                return $this->prefix . " " . $name;
            }
        };
    }
}

namespace {
    use function App\Factory\createGreeter;

    function main() {
        $g = createGreeter("Hello");
        var_dump($g->greet("World"));
        echo "done\n";
    }
}
?>
--EXPECT--
string(11) "Hello World"
done
