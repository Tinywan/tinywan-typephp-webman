<?php
function immutableArrayByRefBuiltin(#[Immutable] array $values): void
{
    sort($values);
}
