--TEST--
Symfony pattern: comparison with coalesce assignment in condition
--FILE--
<?php

function splitLimit(?int $limit): string
{
    if (1 > $limit ??= PHP_INT_MAX) {
        return 'small:'.$limit;
    }

    return 'ok:'.$limit;
}

function main(): void
{
    var_dump(splitLimit(null));
    var_dump(splitLimit(0));
    var_dump(splitLimit(2));
}
?>
--EXPECT--
string(22) "ok:9223372036854775807"
string(7) "small:0"
string(4) "ok:2"
