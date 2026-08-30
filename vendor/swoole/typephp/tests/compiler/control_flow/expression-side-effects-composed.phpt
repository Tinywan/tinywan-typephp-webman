--TEST--
composed ternary and match expressions should evaluate side effects once
--FILE--
<?php

function side_effect(string $label, &$counter, $value) {
    echo $label . ':' . $counter . "\n";
    $counter++;
    return $value;
}

function main() {
    $n = 0;

    $ternary = side_effect('ternary-cond', $n, true)
        ? side_effect('ternary-if', $n, 'yes')
        : side_effect('ternary-else', $n, 'no');

    var_dump($ternary);
    var_dump($n);

    $match = match (side_effect('match-subject', $n, 2)) {
        side_effect('match-arm-1', $n, 1) => side_effect('match-body-1', $n, 'one'),
        side_effect('match-arm-2', $n, 2) => side_effect('match-body-2', $n, 'two'),
        default => side_effect('match-default', $n, 'default'),
    };

    var_dump($match);
    var_dump($n);
}
?>
--EXPECT--
ternary-cond:0
ternary-if:1
string(3) "yes"
int(2)
match-subject:2
match-arm-1:3
match-arm-2:4
match-body-2:5
string(3) "two"
int(6)
