<?php
function phpunit_multi_values(): array
{
    $first = 1;
    $second = 'two';
    return [$first, $second];
}

function phpunit_multi_consumer(): void
{
    [$first, $second] = phpunit_multi_values();
    $array = phpunit_multi_values();

    [$partialFirst, $partialSecond] = phpunit_multi_three_values();
    [$overflowFirst, $overflowSecond, $overflowThird] = phpunit_multi_values();
}

function phpunit_multi_three_values(): array
{
    return [1, 2, 3];
}

function phpunit_multi_repeated_value(): array
{
    $repeated = 'value';
    $tail = 'tail';
    return [$repeated, $repeated, $tail];
}

function phpunit_multi_forward_args(
    mixed $value,
    string $text,
    array $items,
    object $object,
    int $count,
    &$reference,
    ...$rest,
): array {
    return [$value, $text];
}

function phpunit_multi_forward_defaults(
    string $text = 'default',
    array $items = [],
    ...$rest,
): array {
    return [$text, $items];
}

function phpunit_multi_side_effect(): array
{
    return [time(), 2];
}
