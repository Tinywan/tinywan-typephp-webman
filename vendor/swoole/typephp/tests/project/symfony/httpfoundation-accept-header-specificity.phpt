--TEST--
Symfony HttpFoundation pattern: accept header specificity with explode defaults and match
--FILE--
<?php

function specificity(string $rangeValue, int $paramCount, bool $isQueryMedia, bool $isRangeMedia): int
{
    if (!$isQueryMedia && !$isRangeMedia) {
        return ('*' !== $rangeValue ? 2000 : 1000) + $paramCount;
    }

    [$rangeType, $rangeSubtype] = explode('/', $rangeValue, 2) + [1 => '*'];

    $specificity = match (true) {
        '*' !== $rangeSubtype => 3000,
        '*' !== $rangeType => 2000,
        default => 1000,
    };

    return $specificity + $paramCount;
}

function main(): void
{
    var_dump(specificity('text/plain', 2, true, true));
    var_dump(specificity('text/*', 1, true, true));
    var_dump(specificity('*', 0, true, true));
    var_dump(specificity('application/json', 3, false, false));
}
?>
--EXPECT--
int(3002)
int(2001)
int(1000)
int(2003)
