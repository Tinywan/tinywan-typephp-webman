<?php
class TypedArrayPropertyTooManyArguments
{
    #[StdDict(Type::Int, Type::String, Type::Bool)]
    public array $value = [];
}
