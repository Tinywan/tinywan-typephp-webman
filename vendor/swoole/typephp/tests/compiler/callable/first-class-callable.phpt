--TEST--
First-class callable syntax foo(...)
--FILE--
<?php
function multiply(int $a, int $b): int {
    return $a * $b;
}

class Calculator {
    public function add(int $a, int $b): int {
        return $a + $b;
    }

    public static function sub(int $a, int $b): int {
        return $a - $b;
    }
}

function main(): void {
    $mul = multiply(...);
    echo $mul(6, 7) . "\n";

    $calc = new Calculator();
    $add = $calc->add(...);
    echo $add(10, 5) . "\n";

    $sub = Calculator::sub(...);
    echo $sub(10, 3) . "\n";

    var_dump(is_callable('multiply'));
    var_dump(is_callable('nonexistent'));
}
?>
--EXPECT--
42
15
7
bool(true)
bool(false)
