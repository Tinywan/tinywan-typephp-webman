<?php

function typedReferenceRebind(): void
{
    $first = 1;
    $second = 2;
    $alias =& $first;
    $alias =& $second;
}
