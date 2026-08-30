--TEST--
Ternary with captured statements preserves void expression semantics
--FILE--
<?php
function ternary_void_side_effect(string $value): void
{
    echo $value, "\n";
}

function main(): void
{
    $values = [1, 2];

    var_dump(count($values) > 1
        ? ternary_void_side_effect('if')
        : ternary_void_side_effect('else'));
    var_dump(count($values) > 5
        ? ternary_void_side_effect('if')
        : ternary_void_side_effect('else'));
}
?>
--EXPECT--
if
NULL
else
NULL
