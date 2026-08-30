<?php
function dynamic_yield_values(): iterable
{
    yield 'dyn-a' => 11;
    yield 'dyn-b' => 22;
}

function dynamic_yield_from_values(): iterable
{
    yield 'dyn-start' => 0;
    yield from dynamic_yield_values();
    yield 'dyn-end' => 33;
}

function dynamic_collect_iterable(iterable $iterable): array
{
    $result = [];
    foreach ($iterable as $key => $value) {
        $result[] = $key . ':' . $value;
    }
    return $result;
}
