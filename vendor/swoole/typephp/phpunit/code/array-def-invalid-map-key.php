<?php
class ArrayDefInvalidMapKey
{
    #[ArrayDef(Type::Bool, Type::String)]
    public array $value = [];
}
