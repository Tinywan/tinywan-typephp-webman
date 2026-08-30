<?php
class ArrayDefTooManyArguments
{
    #[ArrayDef(Type::Int, Type::String, Type::Bool)]
    public array $value = [];
}
