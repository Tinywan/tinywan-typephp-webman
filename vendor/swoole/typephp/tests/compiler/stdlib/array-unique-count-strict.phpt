--TEST--
count array_unique result for duplicate detection
--FILE--
<?php

function duplicate_count(array $values): int
{
    $allValues = count($values);
    $uniqueValues = count(array_unique($values));
    return $allValues - $uniqueValues;
}

function main(): void
{
    var_dump(duplicate_count(['a', 'b', 'a', 'c', 'b']));
    var_dump(duplicate_count([1, '1', true, 2]));
    var_dump(duplicate_count([]));
}
?>
--EXPECT--
int(2)
int(2)
int(0)
