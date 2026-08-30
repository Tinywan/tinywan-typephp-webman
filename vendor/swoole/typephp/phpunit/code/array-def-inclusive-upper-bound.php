<?php

class ArrayDefInclusiveUpperBound
{
    #[ArrayDef(Type::String)]
    public array $values = [];
}

function writeArrayDefInclusiveUpperBound(ArrayDefInclusiveUpperBound $box, int $index): void
{
    $box->values[$index] = 'indexed';
    $box->values[count($box->values)] = 'counted';
}
