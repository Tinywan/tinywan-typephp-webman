--TEST--
Traditional operators preserve precedence, short-circuiting, values and compound writes
--FILE--
<?php

function traditional_record(array &$events, string $label, bool $result): bool
{
    $events[] = $label;
    return $result;
}

function main(): void
{
    $counter = 3;
    var_dump(--$counter);
    var_dump($counter);

    $lowAnd = true and false;
    $highAnd = true && false;
    $lowOr = false or true;
    $highOr = false || true;
    var_dump($lowAnd, $highAnd, $lowOr, $highOr);

    $events = [];
    $andResult = traditional_record($events, 'and-left', false)
        and traditional_record($events, 'and-right', true);
    $orResult = traditional_record($events, 'or-left', true)
        or traditional_record($events, 'or-right', false);
    var_dump($andResult, $orResult, $events);

    $integer = -7;
    $float = -2.5;
    var_dump(+$integer, +$float);

    var_dump(0b1100 ^ 0b1010);

    $values = [3, 16, 0b1100];
    var_dump($values[0] <<= 3);
    var_dump($values[1] >>= 2);
    var_dump($values[2] ^= 0b1010);
    var_dump($values[2] &= 0b0011);
    var_dump($values);
}
?>
--EXPECT--
int(2)
int(2)
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(true)
array(2) {
  [0]=>
  string(8) "and-left"
  [1]=>
  string(7) "or-left"
}
int(-7)
float(-2.5)
int(6)
int(24)
int(4)
int(6)
int(2)
array(3) {
  [0]=>
  int(24)
  [1]=>
  int(4)
  [2]=>
  int(2)
}
