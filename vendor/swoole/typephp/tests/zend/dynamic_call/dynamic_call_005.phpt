--TEST--
Dynamic calls to scope introspection functions are forbidden
--FILE--
<?php

function test_calls($func) {
    $i = 1;

    try {
        array_map($func, [['i' => new stdClass]]);
        var_dump($i);
    } catch (\Error $e) {
        echo 'array_map: ' . $e->getMessage() . "\n";
    }

    try {
        call_user_func($func, ['i' => new stdClass]);
        var_dump($i);
    } catch (\Error $e) {
        echo 'call_user_func: ' . $e->getMessage() . "\n";
    }

    try {
        $func(['i' => new stdClass]);
        var_dump($i);
    } catch (\Error $e) {
        echo '$func: ' . $e->getMessage() . "\n";
    }
}

function main() {
    test_calls('extract');
}
?>
--EXPECTF--
array_map: Cannot call extract() dynamically
call_user_func: Cannot call extract() dynamically
$func: Cannot call extract() dynamically
