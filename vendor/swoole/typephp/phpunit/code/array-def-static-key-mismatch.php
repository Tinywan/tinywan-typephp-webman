<?php
class ArrayDefStaticKeyMismatch
{
    #[ArrayDef(Type::Int, Type::String)]
    public array $value = [];
}
function arrayDefStaticKeyMismatch(ArrayDefStaticKeyMismatch $box): void
{
    $box->value['bad'] = 'value';
}
