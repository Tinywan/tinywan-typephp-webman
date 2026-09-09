<?php

function typedReferenceConditionalBind(bool $condition): void
{
    $value = 1;
    if ($condition) {
        $alias =& $value;
    }
}
