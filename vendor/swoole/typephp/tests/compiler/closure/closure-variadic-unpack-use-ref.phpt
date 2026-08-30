--TEST--
closure variadic parameter should work with unpacked positional arguments
--FILE--
<?php

function main() {
    $log = [];
    $fn = function ($a, $b = 20, ...$rest) use (&$log) {
        $log[] = $a + $b + array_sum($rest);
        var_dump($a, $b, $rest);
    };

    $fn(...[10, 200, 300, 400]);
    var_dump($log);
}
?>
--EXPECT--
int(10)
int(200)
array(2) {
  [0]=>
  int(300)
  [1]=>
  int(400)
}
array(1) {
  [0]=>
  int(910)
}
