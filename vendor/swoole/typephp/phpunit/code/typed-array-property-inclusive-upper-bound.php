<?php

class TypedArrayPropertyInclusiveUpperBound
{
    #[StdList(Type::String)]
    public array $values = [];
}

function writeTypedArrayPropertyInclusiveUpperBound(TypedArrayPropertyInclusiveUpperBound $box, int $index): void
{
    $box->values[$index] = 'indexed';
    $box->values[count($box->values)] = 'counted';
}
