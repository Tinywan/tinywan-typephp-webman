--TEST--
PHP 8.5 void cast preserves operand side effects in every discarded position
--FILE--
<?php

function void_cast_mark(string $label, int $value): int
{
    echo $label, "\n";
    return $value;
}

function main(): void
{
    $value = 0;
    (void) void_cast_mark('statement', 1);
    (void) ($value = void_cast_mark('assignment operand', 7));
    (void) $value;

    for (
        (void) void_cast_mark('for init', 0), $i = 0;
        (void) @void_cast_mark('for condition', 0), $i < 2;
        (void) void_cast_mark('for loop', 0), $i++
    ) {
        echo 'body:', $i, "\n";
    }

    var_dump($value, $i);
}
?>
--EXPECT--
statement
assignment operand
for init
for condition
body:0
for loop
for condition
body:1
for loop
for condition
int(7)
int(2)
