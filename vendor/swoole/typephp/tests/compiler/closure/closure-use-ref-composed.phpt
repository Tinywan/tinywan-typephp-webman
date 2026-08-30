--TEST--
closure use by reference should work with nested control flow
--FILE--
<?php

function main() {
    $log = [];
    $counter = 0;

    $push = function (string $label, int $value) use (&$log, &$counter): int {
        $log[] = $label . ':' . $counter;
        $counter++;

        return match ($value) {
            1 => $value + $counter,
            default => $counter,
        };
    };

    var_dump($push('a', 1));
    var_dump($push('b', 2));
    var_dump($log);
    var_dump($counter);
}
?>
--EXPECT--
int(2)
int(2)
array(2) {
  [0]=>
  string(3) "a:0"
  [1]=>
  string(3) "b:1"
}
int(2)
