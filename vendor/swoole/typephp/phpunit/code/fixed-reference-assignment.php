<?php

function fixedReferenceAssignment(): void
{
    $value = [];
    $reference =& $value;
}
