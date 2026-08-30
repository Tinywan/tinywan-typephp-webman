<?php

function immutableReference(#[Immutable] array $values): void
{
    $item =& $values[0];
}
