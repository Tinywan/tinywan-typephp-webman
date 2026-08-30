--TEST--
static vars
--FILE--
<?php

function test_fn_static_vars() {
    static $arr = [];
    $arr[] = 100;
    var_dump($arr);
}

function test_fn_static_vars_2() {
    static $var;
    if ($var) {
        var_dump($var);
    } else {
        $var = 999;
        echo "init var\n";
    }
}

function main() {
    test_fn_static_vars();
    test_fn_static_vars();
    test_fn_static_vars_2();
    test_fn_static_vars_2();
}
?>
--EXPECT--
array(1) {
  [0]=>
  int(100)
}
array(2) {
  [0]=>
  int(100)
  [1]=>
  int(100)
}
init var
int(999)
