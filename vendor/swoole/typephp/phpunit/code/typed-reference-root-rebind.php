<?php

function typedReferenceRootRebind(): void
{
    $first = 1;
    $second = 2;
    $alias =& $first;
    $first =& $second;
}
