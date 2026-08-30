--TEST--
__invoke closure-style call and __toString in expressions
--FILE--
<?php
class CallableClass {
    private int $multiplier;

    public function __construct(int $m) {
        $this->multiplier = $m;
    }

    public function __invoke(int $x): int {
        return $x * $this->multiplier;
    }

    public function __toString(): string {
        return "CallableClass(multiplier={$this->multiplier})";
    }
}

class Greeter {
    public function __invoke(string $name): string {
        return "Hello, {$name}!";
    }

    public function __toString(): string {
        return "Greeter";
    }
}

function main(): void {
    $double = new CallableClass(2);
    $triple = new CallableClass(3);

    echo $double(5) . "\n";
    echo $triple(5) . "\n";
    echo $double . "\n";
    echo $triple . "\n";

    $greet = new Greeter();
    echo $greet("World") . "\n";
    echo $greet . "\n";

    // TypePHP is always strict; Stringable objects require an explicit cast
    // when passed to an internal function with a string parameter.
    echo strlen((string) $double) . "\n";
}
?>
--EXPECT--
10
15
CallableClass(multiplier=2)
CallableClass(multiplier=3)
Hello, World!
Greeter
27
