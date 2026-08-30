--TEST--
Generators - Yield keyword and generator functions
--FILE--
<?php
// Test basic generator
function range_generator($start, $end) {
    for ($i = $start; $i <= $end; $i++) {
        yield $i;
    }
}

// Test generator with keys
function keyed_generator() {
    yield 'a' => 1;
    yield 'b' => 2;
    yield 'c' => 3;
}

// Test infinite generator
function infinite_sequence() {
    $i = 1;
    while (true) {
        yield $i++;
        if ($i > 5) break;
    }
}

function main() {
    // Test basic generator
    echo "Basic generator:\n";
    foreach (range_generator(1, 5) as $num) {
        var_dump($num);
    }
    
    // Test keyed generator
    echo "\nKeyed generator:\n";
    foreach (keyed_generator() as $key => $value) {
        echo $key . ": ";
        var_dump($value);
    }
    
    // Test limited infinite generator
    echo "\nInfinite sequence (limited):\n";
    foreach (infinite_sequence() as $num) {
        var_dump($num);
    }
    
    // Test generator object
    $gen = range_generator(10, 12);
    var_dump($gen->valid());
    $gen->rewind();
    var_dump($gen->valid());
    var_dump($gen->current());
    $gen->next();
    var_dump($gen->current());
}
?>
--EXPECT--
Basic generator:
int(1)
int(2)
int(3)
int(4)
int(5)

Keyed generator:
a: int(1)
b: int(2)
c: int(3)

Infinite sequence (limited):
int(1)
int(2)
int(3)
int(4)
int(5)
bool(true)
bool(true)
int(10)
int(11)
