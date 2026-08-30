<?php
class ArrayDefMapAppend
{
    #[ArrayDef(Type::Int, Type::String)]
    public array $value = [];
}
function arrayDefMapAppend(ArrayDefMapAppend $box): void
{
    $box->value[] = 'bad';
}
