<?php
class TypedArrayPropertyStaticKeyMismatch
{
    #[StdDict(Type::Int, Type::String)]
    public array $value = [];
}
function typedArrayPropertyStaticKeyMismatch(TypedArrayPropertyStaticKeyMismatch $box): void
{
    $box->value['bad'] = 'value';
}
