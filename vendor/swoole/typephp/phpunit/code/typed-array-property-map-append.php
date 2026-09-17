<?php
class TypedArrayPropertyMapAppend
{
    #[StdDict(Type::Int, Type::String)]
    public array $value = [];
}
function typedArrayPropertyMapAppend(TypedArrayPropertyMapAppend $box): void
{
    $box->value[] = 'bad';
}
