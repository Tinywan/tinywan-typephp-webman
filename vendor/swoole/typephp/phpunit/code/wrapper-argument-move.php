<?php
function phpunit_wrapper_argument_move(
    mixed $value,
    string $text,
    array $items,
    object $object,
    int $count,
    &$reference,
    ...$rest,
): void {
    $reference = $text;
}

class PhpunitWrapperArgumentMoveTarget
{
    public function consume(string $value): void
    {
    }
}
