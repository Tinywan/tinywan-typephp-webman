<?php

final class TypedReferencePropertyEscapeHolder
{
    public mixed $value;
}

function typedReferencePropertyEscape(): void
{
    $value = 1;
    $holder = new TypedReferencePropertyEscapeHolder();
    $holder->value =& $value;
}
