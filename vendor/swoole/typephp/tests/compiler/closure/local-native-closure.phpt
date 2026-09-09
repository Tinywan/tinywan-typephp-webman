--TEST--
Non-escaping local Closures preserve captures, typed references, checks and argument order
--FILE--
<?php

function main(): void
{
    $base = 10;
    $copy = function (int $value) use ($base): int {
        $base++;
        return $base + $value;
    };
    $base = 100;
    var_dump($copy(1), $copy(1), $base);

    $number = 1;
    $text = 'a';
    $ratio = 1.5;
    $flag = true;
    $items = [];
    $mutate = function () use (&$number, &$text, &$ratio, &$flag, &$items): void {
        $number++;
        $text .= '!';
        $ratio += 0.5;
        $flag = false;
        $items[] = $number;
    };
    $mutate();
    var_dump($number, $text, $ratio, $flag, $items);

    $counter = 0;
    $next = function () use (&$counter): int {
        return $counter++;
    };
    $pair = static fn (int $left, int $right): string => $left . ':' . $right;
    var_dump($pair($next(), $next()), $counter);

    $typed = static fn (int $value): int => $value;
    try {
        $typed(std::any('bad'));
    } catch (TypeError $error) {
        echo "parameter type checked\n";
    }

    $badReturn = static fn (): int => std::any('bad');
    try {
        $badReturn();
    } catch (TypeError $error) {
        echo "return type checked\n";
    }
}
?>
--EXPECT--
int(12)
int(13)
int(100)
int(2)
string(2) "a!"
float(2)
bool(false)
array(1) {
  [0]=>
  int(2)
}
string(3) "0:1"
int(2)
parameter type checked
return type checked
