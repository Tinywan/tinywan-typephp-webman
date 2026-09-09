<?php

function typedReferenceUnsetRoot(): void
{
    $value = 1;
    $alias =& $value;
    unset($value);
}
