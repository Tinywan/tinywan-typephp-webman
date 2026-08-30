--TEST--
Only arrays and countable objects can be counted
--SKIPIF--
--FILE--
<?php

try {
    $result = count(null);
    var_dump($result);
} catch (\TypeError $e) {
    echo $e->getMessage() . \PHP_EOL;
}

try {
    $result = count("string");
    var_dump($result);
} catch (\TypeError $e) {
    echo $e->getMessage() . \PHP_EOL;
}

try {
    $result = count(123);
    var_dump($result);
} catch (\TypeError $e) {
    echo $e->getMessage() . \PHP_EOL;
}

try {
    $result = count(true);
    var_dump($result);
} catch (\TypeError $e) {
    echo $e->getMessage() . \PHP_EOL;
}

try {
    $result = count(false);
    var_dump($result);
} catch (\TypeError $e) {
    echo $e->getMessage() . \PHP_EOL;
}

try {
    $result = count((object) []);
    var_dump($result);
} catch (\TypeError $e) {
    echo $e->getMessage() . \PHP_EOL;
}

?>
--EXPECT--
count(): Argument #1 ($value) must be of type Countable|array
count(): Argument #1 ($value) must be of type Countable|array
count(): Argument #1 ($value) must be of type Countable|array
count(): Argument #1 ($value) must be of type Countable|array
count(): Argument #1 ($value) must be of type Countable|array
int(1)
