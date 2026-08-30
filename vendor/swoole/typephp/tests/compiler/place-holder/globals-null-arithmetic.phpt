--TEST--
NULL globals in arithmetic should treat NULL as 0
--FILE--
<?php
declare(strict_types=1);

// Test 1: NULL global += float
function test_global_add() {
    global $a;
    $a += 0.575;
    var_dump($a);
}

// Test 2: NULL global = float
function test_global_assign() {
    global $b;
    $b = 0.575;
    var_dump($b);
}

// Test 3: Read NULL global, use in subtraction
function test_null_subtraction() {
    global $c;
    var_dump($c - 0.5);  // NULL - float, NULL should be treated as 0
}

// Test 4: NULL global += int
function test_add_int() {
    global $d;
    $d += 5;
    var_dump($d);
}

// Test 5: Full micro_bench simulation
function test_bench_sim() {
    global $total, $last_time;

    // First "end_test" call
    $last_time = 0.575;
    $total += $last_time;
    var_dump($total);
    var_dump($last_time);

    // Read overhead
    $overhead = $last_time;

    // Second "end_test" call
    $last_time = 0.014;
    $total += $last_time;
    $adjusted = $last_time - $overhead;
    var_dump($total);
    var_dump($adjusted);
}

function main(): void {
    test_global_add();
    test_global_assign();
    test_null_subtraction();
    test_add_int();
    test_bench_sim();
}
?>
--EXPECT--
float(0.575)
float(0.575)
float(-0.5)
int(5)
float(0.575)
float(0.575)
float(0.589)
float(-0.5609999999999999)
