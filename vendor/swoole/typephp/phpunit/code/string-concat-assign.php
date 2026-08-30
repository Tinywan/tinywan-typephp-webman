<?php

function stringConcatAssignCodegen(string $suffix): string
{
    $value = '';
    $value .= 'hello';
    $value .= ', ' . $suffix;
    $result = ($value .= $value);
    return $result;
}
