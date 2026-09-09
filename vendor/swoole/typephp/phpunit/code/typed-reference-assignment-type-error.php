<?php

function typedReferenceAssignmentTypeError(): void
{
    $value = 100;
    $reference =& $value;
    $reference = '100';
}
