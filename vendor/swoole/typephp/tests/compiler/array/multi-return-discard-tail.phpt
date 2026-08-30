--TEST--
Tuple multi-return fast path can discard trailing values
--FILE--
<?php
function multi_return_three_values(): array
{
    return [1, 2, 3];
}

function main(): void
{
    [$first, $second] = multi_return_three_values();
    var_dump($first, $second);
}
?>
--EXPECT--
int(1)
int(2)
