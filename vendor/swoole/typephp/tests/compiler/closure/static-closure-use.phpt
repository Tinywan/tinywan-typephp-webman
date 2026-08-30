--TEST--
static closure captures values and references through use
--FILE--
<?php

function main(): void
{
    $base = 10;
    $log = [];

    $copy = static function (int $value) use ($base): int {
        return $base + $value;
    };

    $push = static function (string $label) use (&$log): int {
        $log[] = $label;
        return count($log);
    };

    $base = 99;

    var_dump($copy(5));
    var_dump($push('first'));
    var_dump($push('second'));
    var_dump($log);
}
?>
--EXPECT--
int(15)
int(1)
int(2)
array(2) {
  [0]=>
  string(5) "first"
  [1]=>
  string(6) "second"
}
