--TEST--
Variable functions
--FILE--
<?php

function greet(string $name): string {
    return "Hello, " . $name;
}

function sum(int $a, int $b): int {
    return $a + $b;
}

function main() {
    // Variable function call
    $func = 'greet';
    $result = $func("World");
    var_dump($result);

    // Variable function with multiple args
    $math = 'sum';
    var_dump($math(3, 7));

    // Callable stored in variable
    $callable = 'strlen';
    var_dump($callable("test"));

    // Array of callables
    $ops = ['sum'];
    var_dump($ops[0](10, 20));

    echo "done\n";
}

?>
--EXPECT--
string(12) "Hello, World"
int(10)
int(4)
int(30)
done
