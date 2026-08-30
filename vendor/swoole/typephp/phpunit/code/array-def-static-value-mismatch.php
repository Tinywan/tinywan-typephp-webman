<?php
#[Native]
class ArrayDefStaticValueMismatch
{
    #[ArrayDef(Type::String)]
    public array $value = [];
}
function arrayDefStaticValueMismatch(ArrayDefStaticValueMismatch $box): void
{
    $box->value[] = 123;
}
