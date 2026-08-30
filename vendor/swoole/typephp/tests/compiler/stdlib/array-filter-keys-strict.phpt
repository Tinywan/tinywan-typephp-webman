--TEST--
array keys filtered by closure keep keys for strict comparison
--FILE--
<?php

function has_only_string_keys(array $array): bool
{
    return $array
        && array_keys($array) === array_filter(array_keys($array), function ($key) {
            return is_string($key);
        });
}

function main(): void
{
    var_dump(has_only_string_keys(['a' => 1, 'b' => 2]));
    var_dump(has_only_string_keys(['a' => 1, 0 => 2]));
    var_dump(has_only_string_keys([]));
}
?>
--EXPECT--
bool(true)
bool(false)
bool(false)
