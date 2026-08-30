--TEST--
Fixed list returns use tuple fast path while preserving array semantics
--FILE--
<?php
function multi_values(): array
{
    $a = 1;
    $b = 'two';
    $c = true;
    return [$a, $b, $c];
}

function inferred_multi_values()
{
    return [4, 'five'];
}

function multi_with_default(int $value = 7): array
{
    return [$value, 'default'];
}

function main(): void
{
    [$a, $b, $c] = multi_values();
    var_dump($a, $b, $c);

    $array = multi_values();
    var_dump($array);

    $function = 'multi_values';
    var_dump($function());

    [$x, $y] = multi_values();
    var_dump($x, $y);

    [$h, , $j] = multi_values();
    var_dump($h, $j);

    [$d, $e] = inferred_multi_values();
    var_dump($d, $e);

    [$f, $g] = multi_with_default();
    [$k, $l] = multi_with_default(value: 8);
    var_dump($f, $g, $k, $l);
}
?>
--EXPECT--
int(1)
string(3) "two"
bool(true)
array(3) {
  [0]=>
  int(1)
  [1]=>
  string(3) "two"
  [2]=>
  bool(true)
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  string(3) "two"
  [2]=>
  bool(true)
}
int(1)
string(3) "two"
int(1)
bool(true)
int(4)
string(4) "five"
int(7)
string(7) "default"
int(8)
string(7) "default"
