<?php
#[Native]
class TypedArrayPropertyStaticValueMismatch
{
    #[StdList(Type::String)]
    public array $value = [];
}
function typedArrayPropertyStaticValueMismatch(TypedArrayPropertyStaticValueMismatch $box): void
{
    $box->value[] = 123;
}
