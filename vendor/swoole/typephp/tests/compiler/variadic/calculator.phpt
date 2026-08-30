--TEST--
Variadic Functions - Variable number of arguments with ...
--SKIPIF--
--FILE--
<?php

// Test variadic in class methods
class Calculator {
    public function add(...$values): int {
        return array_sum($values);
    }
    
    public function average(...$values): float {
        if (count($values) === 0) {
            return 0.0;
        }
        return array_sum($values) / count($values);
    }
    
    public static function max(...$values): mixed {
        if (empty($values)) {
            return null;
        }
        return max($values);
    }
}

function main() {

    // Test variadic in class methods
    $calc = new Calculator();
    var_dump($calc->add(1, 2, 3, 4));
    var_dump($calc->average(13, 14, 17, 26));
    var_dump(Calculator::max(5, 15, 10, 20));
}
?>
--EXPECT--
int(10)
float(17.5)
int(20)
