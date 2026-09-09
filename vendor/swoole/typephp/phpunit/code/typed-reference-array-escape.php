<?php

function typedReferenceArrayEscape(): void
{
    $value = 1;
    $array = [];
    $array[] =& $value;
}
