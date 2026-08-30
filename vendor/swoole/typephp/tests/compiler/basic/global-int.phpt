--TEST--
global vars with native types
--FILE--
<?php
use native_types;

function increment_global_int(int $n): void {
    global $global_int;
    for ($i = 0; $i < $n; $i++) {
        $global_int += 1;
    }
}

function increment_global_float(float $v): void {
    global $global_float;
    $global_float += $v;
}

function read_global_int(): int {
    global $global_int;
    return $global_int;
}

function read_global_float(): float {
    global $global_float;
    return $global_float;
}

function main(): void {
    global $global_int, $global_float;
    $global_int = 0;
    $global_float = 0.0;

    increment_global_int(5);
    var_dump(read_global_int());

    increment_global_int(3);
    var_dump(read_global_int());

    increment_global_float(1.5);
    var_dump(read_global_float());

    increment_global_float(2.25);
    var_dump(read_global_float());
}
?>
--EXPECT--
int(5)
int(8)
float(1.5)
float(3.75)
