--TEST--
Ternary with captured statements materializes reference returns as values
--FILE--
<?php
function &ternary_ref_value(mixed &$value): mixed
{
    return $value;
}

function main(): void
{
    $first = 1;
    $second = 2;
    $values = [1];

    $result = count($values) > 0
        ? ternary_ref_value($first)
        : ternary_ref_value($second);
    $result = 9;

    var_dump($first, $second, $result);
}
?>
--EXPECT--
int(1)
int(2)
int(9)
