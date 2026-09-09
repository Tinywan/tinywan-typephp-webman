<?php

function typedReferenceUnsetAlias(): void
{
    $value = 1;
    $alias =& $value;
    unset($alias);
}
