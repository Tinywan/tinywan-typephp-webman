--TEST--
Symfony String style coalesce assignment in comparison condition
--FILE--
<?php
function symfony_string_split_limit(?int $limit = null): int
{
    if (1 > $limit ??= PHP_INT_MAX) {
        throw new InvalidArgumentException('Split limit must be a positive integer.');
    }

    return $limit;
}

function main(): void
{
    var_dump(symfony_string_split_limit());
    var_dump(symfony_string_split_limit(2));

    try {
        symfony_string_split_limit(0);
    } catch (InvalidArgumentException $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
int(9223372036854775807)
int(2)
Split limit must be a positive integer.
