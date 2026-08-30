--TEST--
array keys compared with range for list detection
--FILE--
<?php

function is_non_empty_list_legacy(array $array): bool
{
    return $array && array_keys($array) === range(0, count($array) - 1);
}

function main(): void
{
    var_dump(is_non_empty_list_legacy(['x', 'y']));
    var_dump(is_non_empty_list_legacy([1 => 'x', 2 => 'y']));
    var_dump(is_non_empty_list_legacy(['0' => 'x', '1' => 'y']));
    var_dump(is_non_empty_list_legacy([]));
}
?>
--EXPECT--
bool(true)
bool(false)
bool(true)
bool(false)
